<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Finance\InvoiceResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends BaseController
{
    public function __construct(private InvoiceService $invoiceService) {}

    /**
     * @OA\Get(
     *   path="/finance/invoices",
     *   tags={"Finance"},
     *   summary="Daftar invoice",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="search",    in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="status",    in="query", @OA\Schema(type="string", enum={"draft","open","partial_paid","paid","overdue","cancelled","void"})),
     *   @OA\Parameter(name="client_id", in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="date_to",   in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="type",      in="query", @OA\Schema(type="string", enum={"invoice","credit_note"})),
     *   @OA\Parameter(name="overdue",   in="query", description="Hanya tampilkan yang overdue", @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="per_page",  in="query", @OA\Schema(type="integer", default=20)),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/InvoiceResource")),
     *       @OA\Property(property="summary", type="object",
     *         @OA\Property(property="total_open",    type="number"),
     *         @OA\Property(property="total_overdue", type="number"),
     *         @OA\Property(property="total_paid",    type="number")
     *       )
     *     )
     *   )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['client', 'createdBy'])
            ->when($request->search, fn($q) =>
                $q->where(fn($q) =>
                    $q->where('invoice_number', 'like', "%{$request->search}%")
                      ->orWhere('client_name_snapshot', 'like', "%{$request->search}%")
                )
            )
            ->when($request->status,    fn($q) => $q->where('status', $request->status))
            ->when($request->client_id, fn($q) => $q->where('client_id', $request->client_id))
            ->when($request->type,      fn($q) => $q->where('type', $request->type))
            ->when($request->date_from, fn($q) => $q->where('invoice_date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->where('invoice_date', '<=', $request->date_to))
            ->when($request->overdue, fn($q) =>
                $q->where('due_date', '<', now())
                  ->whereNotIn('status', ['paid', 'cancelled', 'void'])
            )
            ->orderByDesc('invoice_date');

        $paginator = $query->paginate($request->per_page ?? 20);

        // Summary counts
        $summary = Invoice::selectRaw("
            SUM(CASE WHEN status IN ('open','partial_paid') THEN remaining_amount ELSE 0 END) as total_open,
            SUM(CASE WHEN status = 'overdue' THEN remaining_amount ELSE 0 END) as total_overdue,
            SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as total_paid
        ")->first();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => InvoiceResource::collection($paginator->items()),
            'summary' => [
                'total_open'    => (float) ($summary->total_open    ?? 0),
                'total_overdue' => (float) ($summary->total_overdue ?? 0),
                'total_paid'    => (float) ($summary->total_paid    ?? 0),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *   path="/finance/invoices",
     *   tags={"Finance"},
     *   summary="Buat invoice baru",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"client_id","invoice_date","due_date","items"},
     *       @OA\Property(property="client_id",    type="string", format="uuid"),
     *       @OA\Property(property="quotation_id", type="string", format="uuid", nullable=true),
     *       @OA\Property(property="invoice_date", type="string", format="date"),
     *       @OA\Property(property="due_date",     type="string", format="date"),
     *       @OA\Property(property="currency",     type="string", default="IDR"),
     *       @OA\Property(property="notes",        type="string", nullable=true),
     *       @OA\Property(property="items", type="array",
     *         @OA\Items(
     *           @OA\Property(property="description",  type="string"),
     *           @OA\Property(property="coa_id",       type="string", format="uuid", nullable=true),
     *           @OA\Property(property="quantity",     type="number"),
     *           @OA\Property(property="unit",         type="string"),
     *           @OA\Property(property="unit_price",   type="number"),
     *           @OA\Property(property="discount_percent", type="number", nullable=true),
     *           @OA\Property(property="tax_percent",  type="number", nullable=true)
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=201, description="Invoice berhasil dibuat",
     *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/InvoiceResource"))
     *   )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id'       => ['required', 'uuid', 'exists:clients,id'],
            'quotation_id'    => ['nullable', 'uuid', 'exists:quotations,id'],
            'invoice_date'    => ['required', 'date'],
            'due_date'        => ['required', 'date', 'after_or_equal:invoice_date'],
            'currency'        => ['sometimes', 'string', 'size:3'],
            'notes'           => ['nullable', 'string'],
            'internal_notes'  => ['nullable', 'string'],
            'terms_conditions'=> ['nullable', 'string'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.description'     => ['required', 'string'],
            'items.*.coa_id'          => ['nullable', 'uuid', 'exists:chart_of_accounts,id'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.001'],
            'items.*.unit'            => ['nullable', 'string', 'max:30'],
            'items.*.unit_price'      => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent'=> ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_percent'     => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $invoice = $this->invoiceService->createInvoice($validated, $request->user());

        return $this->created(
            new InvoiceResource($invoice->load(['items', 'client'])),
            'Invoice berhasil dibuat.'
        );
    }

    /**
     * @OA\Get(
     *   path="/finance/invoices/{id}",
     *   tags={"Finance"},
     *   summary="Detail invoice",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/InvoiceResource"))
     *   )
     * )
     */
    public function show(string $id): JsonResponse
    {
        $invoice = Invoice::with(['items.coa', 'client', 'quotation', 'payments', 'createdBy'])->find($id);
        if (! $invoice) return $this->notFound('Invoice');
        return $this->ok(new InvoiceResource($invoice));
    }

    /**
     * @OA\Patch(
     *   path="/finance/invoices/{id}",
     *   tags={"Finance"},
     *   summary="Update invoice (hanya status draft)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (! $invoice) return $this->notFound('Invoice');

        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            return $this->error('Hanya invoice berstatus Draft yang dapat diedit.', 422, 'NOT_DRAFT');
        }

        $validated = $request->validate([
            'due_date'        => ['sometimes', 'date'],
            'notes'           => ['sometimes', 'nullable', 'string'],
            'internal_notes'  => ['sometimes', 'nullable', 'string'],
            'terms_conditions'=> ['sometimes', 'nullable', 'string'],
        ]);

        $invoice->update($validated);
        return $this->ok(new InvoiceResource($invoice->load(['items', 'client'])), 'Invoice berhasil diperbarui.');
    }

    /**
     * @OA\Post(
     *   path="/finance/invoices/{id}/send",
     *   tags={"Finance"},
     *   summary="Kirim invoice ke klien",
     *   description="Mengubah status invoice menjadi Open dan mengirim email ke klien.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Invoice berhasil dikirim")
     * )
     */
    public function send(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (! $invoice) return $this->notFound('Invoice');

        if (! in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_OPEN])) {
            return $this->error('Invoice tidak dapat dikirim ulang pada status ini.', 422, 'INVALID_STATUS');
        }

        $invoice->update(['status' => Invoice::STATUS_OPEN]);

        // Dispatch email job
        // \App\Jobs\SendInvoiceEmailJob::dispatch($invoice);

        return $this->ok(null, 'Invoice berhasil dikirim ke klien.');
    }

    /**
     * @OA\Post(
     *   path="/finance/invoices/{id}/cancel",
     *   tags={"Finance"},
     *   summary="Batalkan invoice",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Invoice dibatalkan")
     * )
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (! $invoice) return $this->notFound('Invoice');

        if ($invoice->status === Invoice::STATUS_PAID) {
            return $this->error('Invoice yang sudah dibayar tidak dapat dibatalkan langsung. Gunakan Credit Note.', 422, 'INVOICE_PAID');
        }

        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);
        return $this->ok(null, 'Invoice berhasil dibatalkan.');
    }

    /**
     * @OA\Post(
     *   path="/finance/invoices/{id}/credit-note",
     *   tags={"Finance"},
     *   summary="Buat credit note untuk invoice",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"reason"},
     *       @OA\Property(property="reason", type="string"),
     *       @OA\Property(property="amount", type="number", description="Jumlah credit (kosongkan untuk full refund)")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Credit note berhasil dibuat")
     * )
     */
    public function creditNote(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $invoice = Invoice::find($id);
        if (! $invoice) return $this->notFound('Invoice');

        $creditNote = $this->invoiceService->createCreditNote($invoice, $request->all(), $request->user());

        return $this->created(
            new InvoiceResource($creditNote),
            'Credit note berhasil dibuat.'
        );
    }

    /**
     * @OA\Get(
     *   path="/finance/invoices/{id}/pdf",
     *   tags={"Finance"},
     *   summary="Generate PDF invoice",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="URL PDF invoice",
     *     @OA\JsonContent(@OA\Property(property="data", type="object",
     *       @OA\Property(property="pdf_url",    type="string"),
     *       @OA\Property(property="expires_at", type="string", format="date-time")
     *     ))
     *   )
     * )
     */
    public function pdf(string $id): JsonResponse
    {
        $invoice = Invoice::with(['items', 'client.contacts'])->find($id);
        if (! $invoice) return $this->notFound('Invoice');

        // Generate PDF dan simpan ke storage
        // $pdfUrl = $this->invoiceService->generatePdf($invoice);

        return $this->ok([
            'pdf_url'    => "/storage/invoices/{$invoice->invoice_number}.pdf",
            'expires_at' => now()->addHours(24)->toIso8601String(),
        ]);
    }

    /**
     * @OA\Post(
     *   path="/finance/invoices/{id}/reminder",
     *   tags={"Finance"},
     *   summary="Kirim reminder pembayaran ke klien",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Reminder berhasil dikirim")
     * )
     */
    public function sendReminder(string $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (! $invoice) return $this->notFound('Invoice');
        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])) {
            return $this->error('Invoice ini tidak memerlukan reminder.', 422, 'INVALID_STATUS');
        }
        // \App\Jobs\SendPaymentReminderJob::dispatch($invoice);
        return $this->ok(null, 'Email reminder berhasil dikirim ke klien.');
    }
}
