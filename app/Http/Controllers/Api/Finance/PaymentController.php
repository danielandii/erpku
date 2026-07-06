<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Finance\PaymentResource;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/finance/payments",
     *   tags={"Finance"},
     *   summary="Riwayat pembayaran",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="type",       in="query", @OA\Schema(type="string", enum={"inbound","outbound"})),
     *   @OA\Parameter(name="invoice_id", in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="client_id",  in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="date_from",  in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="date_to",    in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="per_page",   in="query", @OA\Schema(type="integer", default=20)),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PaymentResource"))
     *     )
     *   )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::with(['invoice', 'client', 'bankAccount', 'createdBy'])
            ->when($request->type,       fn($q) => $q->where('type', $request->type))
            ->when($request->invoice_id, fn($q) => $q->where('invoice_id', $request->invoice_id))
            ->when($request->client_id,  fn($q) => $q->where('client_id', $request->client_id))
            ->when($request->date_from,  fn($q) => $q->where('payment_date', '>=', $request->date_from))
            ->when($request->date_to,    fn($q) => $q->where('payment_date', '<=', $request->date_to))
            ->orderByDesc('payment_date')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($payments, PaymentResource::class);
    }

    /**
     * @OA\Post(
     *   path="/finance/payments",
     *   tags={"Finance"},
     *   summary="Catat pembayaran masuk atau keluar",
     *   description="Mencatat pembayaran dan otomatis membuat jurnal double-entry serta memperbarui status invoice.",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"type","payment_date","amount","payment_method"},
     *       @OA\Property(property="type",             type="string", enum={"inbound","outbound"}),
     *       @OA\Property(property="invoice_id",       type="string", format="uuid", nullable=true, description="Wajib jika type=inbound"),
     *       @OA\Property(property="bill_id",          type="string", format="uuid", nullable=true, description="Wajib jika type=outbound ke vendor"),
     *       @OA\Property(property="client_id",        type="string", format="uuid", nullable=true),
     *       @OA\Property(property="vendor_name",      type="string", nullable=true),
     *       @OA\Property(property="payment_date",     type="string", format="date"),
     *       @OA\Property(property="amount",           type="number", example=5000000),
     *       @OA\Property(property="payment_method",   type="string", enum={"bank_transfer","cash","check","giro","qris","virtual_account","credit_card"}),
     *       @OA\Property(property="reference_number", type="string", nullable=true),
     *       @OA\Property(property="bank_account_id",  type="string", format="uuid", nullable=true),
     *       @OA\Property(property="notes",            type="string", nullable=true),
     *       @OA\Property(property="attachment_url",   type="string", nullable=true)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Pembayaran berhasil dicatat",
     *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/PaymentResource"))
     *   ),
     *   @OA\Response(response=422, description="Validasi gagal / jumlah melebihi sisa tagihan")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'             => ['required', 'in:inbound,outbound'],
            'invoice_id'       => ['nullable', 'uuid', 'exists:invoices,id'],
            'bill_id'          => ['nullable', 'uuid', 'exists:bills,id'],
            'client_id'        => ['nullable', 'uuid', 'exists:clients,id'],
            'vendor_name'      => ['nullable', 'string', 'max:255'],
            'payment_date'     => ['required', 'date'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'payment_method'   => ['required', 'in:bank_transfer,cash,check,giro,qris,virtual_account,credit_card'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'bank_account_id'  => ['nullable', 'uuid', 'exists:bank_accounts,id'],
            'notes'            => ['nullable', 'string', 'max:500'],
            'attachment_url'   => ['nullable', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $tenantId = $request->user()->tenant_id;

            // ── Validasi jika inbound: cek invoice ────────────
            $invoice = null;
            if ($validated['type'] === 'inbound' && $validated['invoice_id']) {
                $invoice = Invoice::find($validated['invoice_id']);

                if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED, Invoice::STATUS_VOID])) {
                    return $this->error("Invoice sudah berstatus {$invoice->status}.", 422, 'INVOICE_CLOSED');
                }

                if ($validated['amount'] > $invoice->remaining_amount) {
                    return $this->error(
                        "Jumlah pembayaran (Rp " . number_format($validated['amount']) . ") melebihi sisa tagihan (Rp " . number_format($invoice->remaining_amount) . ").",
                        422, 'OVERPAYMENT'
                    );
                }
            }

            // ── Buat Payment ──────────────────────────────────
            $payment = Payment::create(array_merge($validated, [
                'tenant_id'  => $tenantId,
                'created_by' => $request->user()->id,
            ]));

            // ── Update Invoice Paid Amount ─────────────────────
            if ($invoice) {
                $invoice->increment('paid_amount', $validated['amount']);
                $invoice->decrement('remaining_amount', $validated['amount']);
                $invoice->recalculateStatus();
            }

            // ── Update Bill Paid Amount ────────────────────────
            if ($validated['type'] === 'outbound' && $validated['bill_id']) {
                $bill = \App\Models\Bill::find($validated['bill_id']);
                if ($bill) {
                    $bill->increment('paid_amount', $validated['amount']);
                    $remaining = $bill->amount - $bill->paid_amount;
                    $bill->update([
                        'status' => $remaining <= 0
                            ? \App\Models\Bill::STATUS_PAID
                            : \App\Models\Bill::STATUS_PARTIAL,
                    ]);
                }
            }

            // ── Buat Jurnal Otomatis ──────────────────────────
            $journal = $this->createPaymentJournal($payment, $invoice, $tenantId, $request->user());
            $payment->update(['journal_entry_id' => $journal?->id]);

            // ── Update Saldo Bank Account ──────────────────────
            if ($validated['bank_account_id']) {
                $bankAccount = BankAccount::find($validated['bank_account_id']);
                if ($bankAccount) {
                    if ($validated['type'] === 'inbound') {
                        $bankAccount->increment('current_balance', $validated['amount']);
                    } else {
                        $bankAccount->decrement('current_balance', $validated['amount']);
                    }
                }
            }

            return $this->created(
                new PaymentResource($payment->load(['invoice', 'client', 'bankAccount'])),
                'Pembayaran berhasil dicatat.'
            );
        });
    }

    /**
     * @OA\Get(
     *   path="/finance/payments/{id}",
     *   tags={"Finance"},
     *   summary="Detail pembayaran",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $payment = Payment::with(['invoice', 'client', 'bankAccount', 'journalEntry.lines.coa', 'createdBy'])->find($id);
        if (! $payment) return $this->notFound('Pembayaran');
        return $this->ok(new PaymentResource($payment));
    }

    /**
     * @OA\Delete(
     *   path="/finance/payments/{id}",
     *   tags={"Finance"},
     *   summary="Hapus pembayaran (hanya yang belum di-post)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Pembayaran dihapus")
     * )
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $payment = Payment::find($id);
        if (! $payment) return $this->notFound('Pembayaran');

        // Cek apakah jurnal sudah diposting
        if ($payment->journal_entry_id) {
            $journal = JournalEntry::find($payment->journal_entry_id);
            if ($journal && $journal->is_posted) {
                return $this->error(
                    'Pembayaran yang jurnalnya sudah di-post tidak dapat dihapus. Gunakan reverse jurnal.',
                    422, 'JOURNAL_POSTED'
                );
            }
        }

        return DB::transaction(function () use ($payment) {
            // Rollback invoice paid_amount
            if ($payment->invoice_id) {
                $invoice = Invoice::find($payment->invoice_id);
                if ($invoice) {
                    $invoice->decrement('paid_amount', $payment->amount);
                    $invoice->increment('remaining_amount', $payment->amount);
                    $invoice->recalculateStatus();
                }
            }

            // Rollback bank balance
            if ($payment->bank_account_id) {
                $bank = BankAccount::find($payment->bank_account_id);
                if ($bank) {
                    if ($payment->isInbound()) {
                        $bank->decrement('current_balance', $payment->amount);
                    } else {
                        $bank->increment('current_balance', $payment->amount);
                    }
                }
            }

            $payment->delete();
            return $this->ok(null, 'Pembayaran berhasil dihapus.');
        });
    }

    // ── Private: Auto Journal ──────────────────────────────

    /**
     * Buat jurnal double-entry otomatis untuk pembayaran.
     *
     * Inbound (dari klien):
     *   Dr. Bank/Kas (bank_account COA)
     *   Cr. Piutang Usaha (1201)
     *
     * Outbound (ke vendor/pengeluaran):
     *   Dr. Hutang Usaha / Beban (COA terkait)
     *   Cr. Bank/Kas (bank_account COA)
     */
    private function createPaymentJournal(
        Payment $payment,
        ?Invoice $invoice,
        string $tenantId,
        \App\Models\User $creator
    ): ?JournalEntry {
        $bankCoa = null;
        if ($payment->bank_account_id) {
            $bankAccount = BankAccount::with('coa')->find($payment->bank_account_id);
            $bankCoa     = $bankAccount?->coa;
        }

        if (! $bankCoa) {
            // Fallback: ambil COA Kas (1111) jika tidak ada bank account yang dipilih
            $bankCoa = ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1111')->first();
        }

        if (! $bankCoa) return null;

        $description = $payment->isInbound()
            ? "Penerimaan {$payment->payment_number}" . ($invoice ? " — {$invoice->invoice_number}" : '')
            : "Pengeluaran {$payment->payment_number}" . ($payment->vendor_name ? " — {$payment->vendor_name}" : '');

        $journal = JournalEntry::create([
            'tenant_id'      => $tenantId,
            'type'           => 'payment',
            'reference_type' => 'Payment',
            'reference_id'   => $payment->id,
            'description'    => $description,
            'entry_date'     => $payment->payment_date,
            'period_year'    => \Carbon\Carbon::parse($payment->payment_date)->year,
            'period_month'   => \Carbon\Carbon::parse($payment->payment_date)->month,
            'total_debit'    => $payment->amount,
            'total_credit'   => $payment->amount,
            'created_by'     => $creator->id,
        ]);

        if ($payment->isInbound()) {
            // Dr. Bank / Kas
            $journal->lines()->create([
                'coa_id'        => $bankCoa->id,
                'description'   => $description,
                'debit_amount'  => $payment->amount,
                'credit_amount' => 0,
                'sequence'      => 1,
            ]);

            // Cr. Piutang Usaha
            $arCoa = ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1201')->first();
            if ($arCoa) {
                $journal->lines()->create([
                    'coa_id'        => $arCoa->id,
                    'description'   => "Pelunasan — " . ($invoice?->invoice_number ?? '-'),
                    'debit_amount'  => 0,
                    'credit_amount' => $payment->amount,
                    'sequence'      => 2,
                ]);
            }
        } else {
            // Outbound
            // Dr. Hutang Usaha / Beban
            $apCoa = ChartOfAccount::where('tenant_id', $tenantId)->where('code', '2101')->first();
            if ($apCoa) {
                $journal->lines()->create([
                    'coa_id'        => $apCoa->id,
                    'description'   => $description,
                    'debit_amount'  => $payment->amount,
                    'credit_amount' => 0,
                    'sequence'      => 1,
                ]);
            }

            // Cr. Bank / Kas
            $journal->lines()->create([
                'coa_id'        => $bankCoa->id,
                'description'   => $description,
                'debit_amount'  => 0,
                'credit_amount' => $payment->amount,
                'sequence'      => 2,
            ]);
        }

        $journal->post($creator);
        return $journal;
    }
}
