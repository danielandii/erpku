<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Finance\JournalEntryResource;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Bill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// ══════════════════════════════════════════════════════════
// JournalController
// ══════════════════════════════════════════════════════════
class JournalController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/finance/journals",
     *   tags={"Finance"},
     *   summary="Daftar jurnal umum",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="type",        in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="is_posted",   in="query", @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="date_from",   in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="date_to",     in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="period_year", in="query", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="period_month",in="query", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="per_page",    in="query", @OA\Schema(type="integer", default=20)),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $journals = JournalEntry::with(['createdBy', 'postedBy'])
            ->when($request->type,        fn($q) => $q->where('type', $request->type))
            ->when($request->has('is_posted'), fn($q) =>
                $q->where('is_posted', filter_var($request->is_posted, FILTER_VALIDATE_BOOLEAN))
            )
            ->when($request->date_from,   fn($q) => $q->where('entry_date', '>=', $request->date_from))
            ->when($request->date_to,     fn($q) => $q->where('entry_date', '<=', $request->date_to))
            ->when($request->period_year, fn($q) => $q->where('period_year',  $request->period_year))
            ->when($request->period_month,fn($q) => $q->where('period_month', $request->period_month))
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($journals, JournalEntryResource::class);
    }

    /**
     * @OA\Post(
     *   path="/finance/journals",
     *   tags={"Finance"},
     *   summary="Input jurnal manual",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"description","entry_date","lines"},
     *       @OA\Property(property="description", type="string"),
     *       @OA\Property(property="entry_date",  type="string", format="date"),
     *       @OA\Property(property="lines", type="array",
     *         @OA\Items(
     *           @OA\Property(property="coa_id",        type="string", format="uuid"),
     *           @OA\Property(property="description",   type="string", nullable=true),
     *           @OA\Property(property="debit_amount",  type="number"),
     *           @OA\Property(property="credit_amount", type="number")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=201, description="Jurnal berhasil dibuat")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description'         => ['required', 'string', 'max:500'],
            'entry_date'          => ['required', 'date'],
            'lines'               => ['required', 'array', 'min:2'],
            'lines.*.coa_id'      => ['required', 'uuid', 'exists:chart_of_accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit_amount'=> ['required', 'numeric', 'min:0'],
            'lines.*.credit_amount'=>['required', 'numeric', 'min:0'],
        ]);

        $totalDebit  = collect($validated['lines'])->sum('debit_amount');
        $totalCredit = collect($validated['lines'])->sum('credit_amount');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return $this->error(
                "Jurnal tidak balance. Total Debit: {$totalDebit}, Total Kredit: {$totalCredit}.",
                422, 'JOURNAL_NOT_BALANCED'
            );
        }

        $journal = JournalEntry::create([
            'tenant_id'    => $request->user()->tenant_id,
            'type'         => 'manual',
            'description'  => $validated['description'],
            'entry_date'   => $validated['entry_date'],
            'period_year'  => \Carbon\Carbon::parse($validated['entry_date'])->year,
            'period_month' => \Carbon\Carbon::parse($validated['entry_date'])->month,
            'total_debit'  => $totalDebit,
            'total_credit' => $totalCredit,
            'created_by'   => $request->user()->id,
        ]);

        foreach ($validated['lines'] as $i => $line) {
            $journal->lines()->create(array_merge($line, ['sequence' => $i + 1]));
        }

        return $this->created(
            new JournalEntryResource($journal->load('lines.coa')),
            'Jurnal berhasil dibuat. Klik "Post" untuk memposting ke General Ledger.'
        );
    }

    /**
     * @OA\Get(
     *   path="/finance/journals/{id}",
     *   tags={"Finance"},
     *   summary="Detail jurnal",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $journal = JournalEntry::with(['lines.coa', 'createdBy', 'postedBy', 'reversedByEntry'])->find($id);
        if (! $journal) return $this->notFound('Jurnal');
        return $this->ok(new JournalEntryResource($journal));
    }

    /**
     * @OA\Post(
     *   path="/finance/journals/{id}/post",
     *   tags={"Finance"},
     *   summary="Post jurnal ke General Ledger",
     *   description="Setelah di-post, jurnal tidak dapat diedit. Hanya bisa di-reverse.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Jurnal berhasil di-post")
     * )
     */
    public function post(Request $request, string $id): JsonResponse
    {
        $journal = JournalEntry::find($id);
        if (! $journal) return $this->notFound('Jurnal');

        if ($journal->is_posted) {
            return $this->error('Jurnal ini sudah di-post sebelumnya.', 409, 'ALREADY_POSTED');
        }

        try {
            $journal->post($request->user());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422, 'POST_FAILED');
        }

        return $this->ok(new JournalEntryResource($journal->fresh()), 'Jurnal berhasil di-post ke General Ledger.');
    }

    /**
     * @OA\Post(
     *   path="/finance/journals/{id}/reverse",
     *   tags={"Finance"},
     *   summary="Reverse jurnal yang sudah di-post",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       @OA\Property(property="reason", type="string", description="Alasan reverse")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Jurnal reverse berhasil dibuat")
     * )
     */
    public function reverse(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $journal = JournalEntry::find($id);
        if (! $journal) return $this->notFound('Jurnal');

        if ($journal->is_reversed) {
            return $this->error('Jurnal ini sudah di-reverse sebelumnya.', 409, 'ALREADY_REVERSED');
        }

        try {
            $reversal = $journal->reverse($request->user(), $request->reason ?? '');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422, 'REVERSE_FAILED');
        }

        return $this->created(
            new JournalEntryResource($reversal->load('lines.coa')),
            'Jurnal reverse berhasil dibuat dan otomatis di-post.'
        );
    }
}

// ══════════════════════════════════════════════════════════
// CoaController
// ══════════════════════════════════════════════════════════
class CoaController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/finance/coa",
     *   tags={"Finance"},
     *   summary="Daftar Chart of Accounts",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="account_type", in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="is_detail",    in="query", @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="is_active",    in="query", @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="search",       in="query", @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $accounts = ChartOfAccount::query()
            ->when($request->account_type, fn($q) => $q->where('account_type', $request->account_type))
            ->when($request->has('is_detail'), fn($q) =>
                $q->where('is_detail', filter_var($request->is_detail, FILTER_VALIDATE_BOOLEAN))
            )
            ->when($request->has('is_active'), fn($q) =>
                $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->when($request->search, fn($q) =>
                $q->where(fn($q) =>
                    $q->where('name', 'like', "%{$request->search}%")
                      ->orWhere('code', 'like', "%{$request->search}%")
                )
            )
            ->orderBy('code')
            ->paginate($request->per_page ?? 100);

        return $this->paginated($accounts, \App\Http\Resources\Finance\CoaResource::class);
    }

    /**
     * @OA\Get(
     *   path="/finance/coa/tree",
     *   tags={"Finance"},
     *   summary="COA dalam format hierarki tree",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function tree(Request $request): JsonResponse
    {
        $accounts = ChartOfAccount::where('is_active', true)
            ->orderBy('code')
            ->get();

        $tree = $this->buildTree($accounts);
        return $this->ok($tree);
    }

    /**
     * @OA\Get(
     *   path="/finance/coa/{id}",
     *   tags={"Finance"},
     *   summary="Detail akun COA",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $coa = ChartOfAccount::with(['parent', 'children'])->find($id);
        if (! $coa) return $this->notFound('Akun COA');
        return $this->ok(new \App\Http\Resources\Finance\CoaResource($coa));
    }

    /**
     * @OA\Post(
     *   path="/finance/coa",
     *   tags={"Finance"},
     *   summary="Tambah akun COA baru",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"code","name","account_type","normal_balance"},
     *       @OA\Property(property="code",           type="string", example="4201"),
     *       @OA\Property(property="name",           type="string", example="Pendapatan Konsultasi Baru"),
     *       @OA\Property(property="account_type",   type="string", enum={"asset","liability","equity","revenue","cogs","expense","other_revenue","other_expense"}),
     *       @OA\Property(property="normal_balance", type="string", enum={"debit","credit"}),
     *       @OA\Property(property="parent_id",      type="string", format="uuid", nullable=true),
     *       @OA\Property(property="is_detail",      type="boolean", default=true),
     *       @OA\Property(property="description",    type="string", nullable=true)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Akun berhasil ditambahkan")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'code'           => ['required', 'string', 'max:20'],
            'name'           => ['required', 'string', 'max:255'],
            'account_type'   => ['required', 'in:asset,liability,equity,revenue,cogs,expense,other_revenue,other_expense'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'parent_id'      => ['nullable', 'uuid', 'exists:chart_of_accounts,id'],
            'is_detail'      => ['sometimes', 'boolean'],
            'description'    => ['nullable', 'string'],
        ]);

        // Cek duplikasi kode
        $exists = ChartOfAccount::where('tenant_id', $tenantId)->where('code', $validated['code'])->exists();
        if ($exists) {
            return $this->error("Kode akun '{$validated['code']}' sudah digunakan.", 409, 'COA_CODE_EXISTS');
        }

        $level = 1;
        if ($validated['parent_id']) {
            $parent = ChartOfAccount::find($validated['parent_id']);
            $level  = ($parent->level ?? 1) + 1;
        }

        $coa = ChartOfAccount::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'level'     => $level,
            'is_active' => true,
        ]));

        return $this->created(
            new \App\Http\Resources\Finance\CoaResource($coa),
            'Akun COA berhasil ditambahkan.'
        );
    }

    /**
     * @OA\Patch(
     *   path="/finance/coa/{id}",
     *   tags={"Finance"},
     *   summary="Update akun COA",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $coa = ChartOfAccount::find($id);
        if (! $coa) return $this->notFound('Akun COA');

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $coa->update($validated);
        return $this->ok(new \App\Http\Resources\Finance\CoaResource($coa), 'Akun COA berhasil diperbarui.');
    }

    /**
     * @OA\Delete(
     *   path="/finance/coa/{id}",
     *   tags={"Finance"},
     *   summary="Nonaktifkan akun COA",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Akun dinonaktifkan")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $coa = ChartOfAccount::find($id);
        if (! $coa) return $this->notFound('Akun COA');

        $hasTransactions = $coa->journalLines()->exists();
        if ($hasTransactions) {
            $coa->update(['is_active' => false]);
            return $this->ok(null, 'Akun memiliki transaksi dan telah dinonaktifkan (tidak dihapus).');
        }

        $coa->delete();
        return $this->ok(null, 'Akun COA berhasil dihapus.');
    }

    /**
     * @OA\Get(
     *   path="/finance/coa/{id}/balance",
     *   tags={"Finance"},
     *   summary="Saldo akun COA",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id",      in="path",  required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="as_of",   in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="balance",        type="number"),
     *         @OA\Property(property="total_debit",    type="number"),
     *         @OA\Property(property="total_credit",   type="number"),
     *         @OA\Property(property="normal_balance", type="string")
     *       )
     *     )
     *   )
     * )
     */
    public function balance(Request $request, string $id): JsonResponse
    {
        $coa   = ChartOfAccount::find($id);
        if (! $coa) return $this->notFound('Akun COA');

        $asOf    = $request->as_of ? \Carbon\Carbon::parse($request->as_of) : null;
        $balance = $coa->getBalance($asOf);

        return $this->ok([
            'code'           => $coa->code,
            'name'           => $coa->name,
            'normal_balance' => $coa->normal_balance,
            'balance'        => $balance,
            'as_of'          => $asOf?->toDateString() ?? 'Sekarang',
        ]);
    }

    private function buildTree(\Illuminate\Support\Collection $accounts, ?string $parentId = null): array
    {
        return $accounts
            ->where('parent_id', $parentId)
            ->map(fn($acc) => [
                'id'           => $acc->id,
                'code'         => $acc->code,
                'name'         => $acc->name,
                'account_type' => $acc->account_type,
                'level'        => $acc->level,
                'is_detail'    => $acc->is_detail,
                'is_active'    => $acc->is_active,
                'children'     => $this->buildTree($accounts, $acc->id),
            ])
            ->values()
            ->toArray();
    }
}

// ══════════════════════════════════════════════════════════
// BillController
// ══════════════════════════════════════════════════════════
class BillController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/finance/bills",
     *   tags={"Finance"},
     *   summary="Daftar tagihan vendor (AP)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="status",    in="query", @OA\Schema(type="string", enum={"unpaid","partial","paid","overdue","cancelled"})),
     *   @OA\Parameter(name="vendor",    in="query", description="Search by vendor name", @OA\Schema(type="string")),
     *   @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="date_to",   in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="per_page",  in="query", @OA\Schema(type="integer", default=20)),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $bills = Bill::with(['coa', 'createdBy', 'approvedBy'])
            ->when($request->status,    fn($q) => $q->where('status', $request->status))
            ->when($request->vendor,    fn($q) => $q->where('vendor_name', 'like', "%{$request->vendor}%"))
            ->when($request->date_from, fn($q) => $q->where('bill_date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->where('bill_date', '<=', $request->date_to))
            ->orderByDesc('due_date')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($bills, \App\Http\Resources\Finance\BillResource::class);
    }

    /**
     * @OA\Post(
     *   path="/finance/bills",
     *   tags={"Finance"},
     *   summary="Input tagihan vendor baru",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"vendor_name","bill_date","due_date","description","coa_id","amount"},
     *       @OA\Property(property="vendor_name",           type="string"),
     *       @OA\Property(property="vendor_invoice_number", type="string", nullable=true),
     *       @OA\Property(property="bill_date",             type="string", format="date"),
     *       @OA\Property(property="due_date",              type="string", format="date"),
     *       @OA\Property(property="description",           type="string"),
     *       @OA\Property(property="coa_id",                type="string", format="uuid"),
     *       @OA\Property(property="amount",                type="number"),
     *       @OA\Property(property="attachment_url",        type="string", nullable=true),
     *       @OA\Property(property="notes",                 type="string", nullable=true)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Tagihan vendor berhasil ditambahkan")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_name'           => ['required', 'string', 'max:255'],
            'vendor_invoice_number' => ['nullable', 'string', 'max:100'],
            'bill_date'             => ['required', 'date'],
            'due_date'              => ['required', 'date', 'after_or_equal:bill_date'],
            'description'           => ['required', 'string', 'max:500'],
            'coa_id'                => ['required', 'uuid', 'exists:chart_of_accounts,id'],
            'amount'                => ['required', 'numeric', 'min:0.01'],
            'attachment_url'        => ['nullable', 'string', 'max:500'],
            'notes'                 => ['nullable', 'string', 'max:500'],
        ]);

        $bill = Bill::create(array_merge($validated, [
            'tenant_id'  => $request->user()->tenant_id,
            'status'     => Bill::STATUS_UNPAID,
            'created_by' => $request->user()->id,
        ]));

        return $this->created(
            new \App\Http\Resources\Finance\BillResource($bill->load('coa')),
            'Tagihan vendor berhasil ditambahkan.'
        );
    }

    /**
     * @OA\Get(
     *   path="/finance/bills/{id}",
     *   tags={"Finance"},
     *   summary="Detail tagihan vendor",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $bill = Bill::with(['coa', 'payments', 'createdBy', 'approvedBy'])->find($id);
        if (! $bill) return $this->notFound('Tagihan vendor');
        return $this->ok(new \App\Http\Resources\Finance\BillResource($bill));
    }

    /**
     * @OA\Patch(
     *   path="/finance/bills/{id}",
     *   tags={"Finance"},
     *   summary="Update tagihan vendor",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $bill = Bill::find($id);
        if (! $bill) return $this->notFound('Tagihan vendor');

        if ($bill->status === Bill::STATUS_PAID) {
            return $this->error('Tagihan yang sudah dibayar tidak dapat diedit.', 422, 'BILL_PAID');
        }

        $validated = $request->validate([
            'due_date'       => ['sometimes', 'date'],
            'description'    => ['sometimes', 'string', 'max:500'],
            'amount'         => ['sometimes', 'numeric', 'min:0.01'],
            'attachment_url' => ['sometimes', 'nullable', 'string'],
            'notes'          => ['sometimes', 'nullable', 'string'],
        ]);

        $bill->update($validated);
        return $this->ok(new \App\Http\Resources\Finance\BillResource($bill), 'Tagihan berhasil diperbarui.');
    }

    /**
     * @OA\Post(
     *   path="/finance/bills/{id}/approve",
     *   tags={"Finance"},
     *   summary="Approve tagihan vendor (izinkan pembayaran)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Tagihan disetujui")
     * )
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $bill = Bill::find($id);
        if (! $bill) return $this->notFound('Tagihan vendor');

        $bill->update([
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return $this->ok(null, 'Tagihan vendor disetujui untuk dibayar.');
    }

    /**
     * @OA\Post(
     *   path="/finance/bills/{id}/cancel",
     *   tags={"Finance"},
     *   summary="Batalkan tagihan vendor",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Tagihan dibatalkan")
     * )
     */
    public function cancel(string $id): JsonResponse
    {
        $bill = Bill::find($id);
        if (! $bill) return $this->notFound('Tagihan vendor');

        if ($bill->paid_amount > 0) {
            return $this->error('Tagihan yang sudah ada pembayarannya tidak dapat dibatalkan langsung.', 422, 'HAS_PAYMENT');
        }

        $bill->update(['status' => Bill::STATUS_CANCELLED]);
        return $this->ok(null, 'Tagihan vendor berhasil dibatalkan.');
    }
}
