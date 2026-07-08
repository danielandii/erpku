<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Marketing\QuotationResource;
use App\Http\Resources\Marketing\ClientResource;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// ══════════════════════════════════════════════════════════
// QuotationController
// ══════════════════════════════════════════════════════════
class QuotationController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/marketing/quotations",
     *   tags={"Marketing"},
     *   summary="Daftar quotation",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="search",    in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="status",    in="query", @OA\Schema(type="string", enum={"draft","sent","confirmed","invoiced","expired","cancelled"})),
     *   @OA\Parameter(name="client_id", in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="lead_id",   in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="date_to",   in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="per_page",  in="query", @OA\Schema(type="integer", default=20)),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/QuotationResource")))
     *   )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $quotations = Quotation::with(['client', 'pic', 'createdBy'])
            ->when($request->search, fn($q) =>
                $q->where(fn($q) =>
                    $q->where('quotation_number', 'like', "%{$request->search}%")
                      ->orWhere('client_name_snapshot', 'like', "%{$request->search}%")
                )
            )
            ->when($request->status,    fn($q) => $q->where('status', $request->status))
            ->when($request->client_id, fn($q) => $q->where('client_id', $request->client_id))
            ->when($request->lead_id,   fn($q) => $q->where('lead_id', $request->lead_id))
            ->when($request->date_from, fn($q) => $q->where('date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->where('date', '<=', $request->date_to))
            ->orderByDesc('date')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($quotations, QuotationResource::class);
    }

    /**
     * @OA\Post(
     *   path="/marketing/quotations",
     *   tags={"Marketing"},
     *   summary="Buat quotation baru",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"client_id","date","items"},
     *       @OA\Property(property="lead_id",          type="string", format="uuid", nullable=true),
     *       @OA\Property(property="client_id",        type="string", format="uuid"),
     *       @OA\Property(property="date",             type="string", format="date"),
     *       @OA\Property(property="valid_until",      type="string", format="date", nullable=true),
     *       @OA\Property(property="pic_user_id",      type="string", format="uuid", nullable=true),
     *       @OA\Property(property="currency",         type="string", default="IDR"),
     *       @OA\Property(property="discount_percent", type="number", nullable=true),
     *       @OA\Property(property="terms_conditions", type="string", nullable=true),
     *       @OA\Property(property="notes",            type="string", nullable=true),
     *       @OA\Property(property="items", type="array",
     *         @OA\Items(
     *           @OA\Property(property="description",      type="string"),
     *           @OA\Property(property="quantity",         type="number"),
     *           @OA\Property(property="unit",             type="string", nullable=true),
     *           @OA\Property(property="unit_price",       type="number"),
     *           @OA\Property(property="discount_percent", type="number", nullable=true),
     *           @OA\Property(property="tax_percent",      type="number", nullable=true)
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=201, description="Quotation berhasil dibuat",
     *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/QuotationResource"))
     *   )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lead_id'          => ['nullable', 'uuid', 'exists:leads,id'],
            'client_id'        => ['required', 'uuid', 'exists:clients,id'],
            'date'             => ['required', 'date'],
            'valid_until'      => ['nullable', 'date', 'after_or_equal:date'],
            'pic_user_id'      => ['nullable', 'uuid', 'exists:users,id'],
            'currency'         => ['sometimes', 'string', 'size:3'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'terms_conditions' => ['nullable', 'string'],
            'notes'            => ['nullable', 'string'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.description'      => ['required', 'string'],
            'items.*.quantity'         => ['required', 'numeric', 'min:0.001'],
            'items.*.unit'             => ['nullable', 'string', 'max:30'],
            'items.*.unit_price'       => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_percent'      => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $client = Client::find($validated['client_id']);

        // Kalkulasi totals
        $subtotal   = 0;
        $discHeader = 0;
        $taxTotal   = 0;
        $itemsData  = [];

        foreach ($validated['items'] as $i => $item) {
            $qty      = (float) $item['quantity'];
            $price    = (float) $item['unit_price'];
            $discPct  = (float) ($item['discount_percent'] ?? 0);
            $taxPct   = (float) ($item['tax_percent'] ?? 11);

            $iSubtotal    = $qty * $price;
            $iDisc        = round($iSubtotal * $discPct / 100, 2);
            $iTaxBase     = $iSubtotal - $iDisc;
            $iTax         = round($iTaxBase * $taxPct / 100, 2);
            $iTotal       = $iTaxBase + $iTax;

            $subtotal  += $iSubtotal;
            $taxTotal  += $iTax;

            $itemsData[] = [
                'sequence'         => $i + 1,
                'description'      => $item['description'],
                'quantity'         => $qty,
                'unit'             => $item['unit'] ?? null,
                'unit_price'       => $price,
                'discount_percent' => $discPct,
                'tax_percent'      => $taxPct,
                'subtotal'         => $iSubtotal,
                'discount_amount'  => $iDisc,
                'tax_amount'       => $iTax,
                'total'            => $iTotal,
            ];
        }

        // Diskon header
        $discPctHeader = (float) ($validated['discount_percent'] ?? 0);
        $discHeader    = round($subtotal * $discPctHeader / 100, 2);
        $totalAmount   = $subtotal - $discHeader + $taxTotal;

        $quotation = Quotation::create([
            'tenant_id'               => $request->user()->tenant_id,
            'lead_id'                 => $validated['lead_id'] ?? null,
            'client_id'               => $client->id,
            'client_name_snapshot'    => $client->name,
            'client_address_snapshot' => $client->address,
            'date'                    => $validated['date'],
            'valid_until'             => $validated['valid_until'] ?? null,
            'pic_user_id'             => $validated['pic_user_id'] ?? $request->user()->id,
            'currency'                => $validated['currency'] ?? 'IDR',
            'subtotal'                => $subtotal,
            'discount_percent'        => $discPctHeader,
            'discount_amount'         => $discHeader,
            'tax_amount'              => $taxTotal,
            'total_amount'            => $totalAmount,
            'terms_conditions'        => $validated['terms_conditions'] ?? null,
            'notes'                   => $validated['notes'] ?? null,
            'status'                  => Quotation::STATUS_DRAFT,
            'created_by'              => $request->user()->id,
        ]);

        foreach ($itemsData as $itemData) {
            $quotation->items()->create($itemData);
        }

        return $this->created(
            new QuotationResource($quotation->load(['items', 'client', 'pic'])),
            'Quotation berhasil dibuat.'
        );
    }

    /**
     * @OA\Get(
     *   path="/marketing/quotations/{id}",
     *   tags={"Marketing"},
     *   summary="Detail quotation",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $quotation = Quotation::with(['items', 'client', 'lead', 'pic', 'createdBy', 'revisions'])->find($id);
        if (! $quotation) return $this->notFound('Quotation');
        return $this->ok(new QuotationResource($quotation));
    }

    /**
     * @OA\Patch(
     *   path="/marketing/quotations/{id}",
     *   tags={"Marketing"},
     *   summary="Update quotation (hanya draft)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $quotation = Quotation::find($id);
        if (! $quotation) return $this->notFound('Quotation');

        if ($quotation->status !== Quotation::STATUS_DRAFT) {
            return $this->error('Hanya quotation berstatus Draft yang dapat diedit.', 422, 'NOT_DRAFT');
        }

        $validated = $request->validate([
            'valid_until'      => ['sometimes', 'nullable', 'date'],
            'terms_conditions' => ['sometimes', 'nullable', 'string'],
            'notes'            => ['sometimes', 'nullable', 'string'],
            'pic_user_id'      => ['sometimes', 'nullable', 'uuid'],
        ]);

        $quotation->update($validated);
        return $this->ok(new QuotationResource($quotation->load(['items', 'client'])), 'Quotation berhasil diperbarui.');
    }

    /** @OA\Delete(path="/marketing/quotations/{id}", tags={"Marketing"}, summary="Hapus/cancel quotation", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $quotation = Quotation::find($id);
        if (! $quotation) return $this->notFound('Quotation');

        if (in_array($quotation->status, [Quotation::STATUS_CONFIRMED, Quotation::STATUS_INVOICED])) {
            return $this->error('Quotation yang sudah dikonfirmasi tidak dapat dihapus.', 422, 'CANNOT_DELETE');
        }

        $quotation->update(['status' => Quotation::STATUS_CANCELLED]);
        $quotation->delete();
        return $this->ok(null, 'Quotation berhasil dibatalkan.');
    }

    /** @OA\Post(path="/marketing/quotations/{id}/send", tags={"Marketing"}, summary="Kirim quotation ke klien",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Quotation dikirim")
     * )
     */
    public function send(string $id): JsonResponse
    {
        $quotation = Quotation::find($id);
        if (! $quotation) return $this->notFound('Quotation');

        if (! in_array($quotation->status, [Quotation::STATUS_DRAFT, Quotation::STATUS_SENT])) {
            return $this->error('Quotation tidak dapat dikirim pada status ini.', 422, 'INVALID_STATUS');
        }

        $quotation->update(['status' => Quotation::STATUS_SENT, 'sent_at' => now()]);
        // \App\Jobs\SendQuotationEmailJob::dispatch($quotation);

        return $this->ok(null, 'Quotation berhasil dikirim ke klien.');
    }

    /** @OA\Post(path="/marketing/quotations/{id}/confirm", tags={"Marketing"}, summary="Konfirmasi quotation (PO diterima)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(@OA\JsonContent(@OA\Property(property="po_number", type="string", nullable=true))),
     *   @OA\Response(response=200, description="Quotation dikonfirmasi")
     * )
     */
    public function confirm(Request $request, string $id): JsonResponse
    {
        $request->validate(['po_number' => ['nullable', 'string', 'max:100']]);

        $quotation = Quotation::find($id);
        if (! $quotation) return $this->notFound('Quotation');

        if ($quotation->status !== Quotation::STATUS_SENT) {
            return $this->error('Hanya quotation yang sudah dikirim yang bisa dikonfirmasi.', 422, 'NOT_SENT');
        }

        $quotation->update([
            'status'       => Quotation::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'po_number'    => $request->po_number,
        ]);

        return $this->ok(null, 'Quotation berhasil dikonfirmasi. Siap untuk dibuat Invoice.');
    }

    /** @OA\Post(path="/marketing/quotations/{id}/revise", tags={"Marketing"}, summary="Buat revisi quotation",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=201, description="Revisi berhasil dibuat")
     * )
     */
    public function revise(Request $request, string $id): JsonResponse
    {
        $original = Quotation::with('items')->find($id);
        if (! $original) return $this->notFound('Quotation');

        if ($original->status === Quotation::STATUS_INVOICED) {
            return $this->error('Quotation yang sudah diinvoice tidak bisa direvisi.', 422, 'ALREADY_INVOICED');
        }

        $revision = $original->replicate();
        $revision->parent_id        = $original->id;
        $revision->version          = $original->version + 1;
        $revision->status           = Quotation::STATUS_DRAFT;
        $revision->sent_at          = null;
        $revision->confirmed_at     = null;
        $revision->po_number        = null;
        $revision->created_by       = $request->user()->id;
        $revision->save();

        foreach ($original->items as $item) {
            $newItem = $item->replicate();
            $newItem->quotation_id = $revision->id;
            $newItem->save();
        }

        return $this->created(
            new QuotationResource($revision->load(['items', 'client'])),
            "Revisi v{$revision->version} berhasil dibuat."
        );
    }

    /** @OA\Get(path="/marketing/quotations/{id}/pdf", tags={"Marketing"}, summary="Generate PDF quotation",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="URL PDF")
     * )
     */
    public function pdf(string $id): JsonResponse
    {
        $quotation = Quotation::with(['items', 'client'])->find($id);
        if (! $quotation) return $this->notFound('Quotation');

        return $this->ok([
            'pdf_url'    => "/storage/quotations/{$quotation->quotation_number}.pdf",
            'expires_at' => now()->addHours(24)->toIso8601String(),
        ]);
    }

    /** @OA\Post(path="/marketing/quotations/{id}/to-invoice", tags={"Marketing"}, summary="Konversi quotation ke invoice",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(@OA\JsonContent(@OA\Property(property="invoice_date", type="string", format="date"), @OA\Property(property="due_date", type="string", format="date"))),
     *   @OA\Response(response=201, description="Invoice berhasil dibuat dari quotation")
     * )
     */
    public function convertToInvoice(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'invoice_date' => ['required', 'date'],
            'due_date'     => ['required', 'date', 'after_or_equal:invoice_date'],
        ]);

        $quotation = Quotation::with('items')->find($id);
        if (! $quotation) return $this->notFound('Quotation');

        if (! $quotation->canBeInvoiced()) {
            return $this->error('Hanya quotation berstatus Confirmed yang dapat dikonversi ke Invoice.', 422, 'NOT_CONFIRMED');
        }

        $invoiceService = app(\App\Services\Finance\InvoiceService::class);
        $invoice = $invoiceService->createInvoice([
            'client_id'    => $quotation->client_id,
            'quotation_id' => $quotation->id,
            'invoice_date' => $validated['invoice_date'],
            'due_date'     => $validated['due_date'],
            'currency'     => $quotation->currency,
            'notes'        => $quotation->notes,
            'items'        => $quotation->items->map(fn($item) => [
                'description'      => $item->description,
                'quantity'         => $item->quantity,
                'unit'             => $item->unit,
                'unit_price'       => $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'tax_percent'      => $item->tax_percent,
            ])->toArray(),
        ], $request->user());

        $quotation->update(['status' => Quotation::STATUS_INVOICED]);

        return $this->created([
            'invoice_id'     => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ], 'Invoice berhasil dibuat dari quotation.');
    }
}

// ══════════════════════════════════════════════════════════
// ClientController
// ══════════════════════════════════════════════════════════
// class ClientController extends BaseController
// {
//     /**
//      * @OA\Get(
//      *   path="/marketing/clients",
//      *   tags={"Marketing"},
//      *   summary="Daftar klien",
//      *   security={{"bearerAuth":{}}},
//      *   @OA\Parameter(name="search",      in="query", @OA\Schema(type="string")),
//      *   @OA\Parameter(name="segment",     in="query", @OA\Schema(type="string")),
//      *   @OA\Parameter(name="assigned_to", in="query", @OA\Schema(type="string", format="uuid")),
//      *   @OA\Parameter(name="is_active",   in="query", @OA\Schema(type="boolean")),
//      *   @OA\Parameter(name="per_page",    in="query", @OA\Schema(type="integer", default=20)),
//      *   @OA\Response(response=200, description="OK",
//      *     @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ClientResource")))
//      *   )
//      * )
//      */
//     public function index(Request $request): JsonResponse
//     {
//         $clients = Client::with(['contacts', 'assignedTo'])
//             ->when($request->search, fn($q) =>
//                 $q->where(fn($q) =>
//                     $q->where('name', 'like', "%{$request->search}%")
//                       ->orWhere('client_number', 'like', "%{$request->search}%")
//                       ->orWhere('email', 'like', "%{$request->search}%")
//                 )
//             )
//             ->when($request->segment,     fn($q) => $q->where('segment', $request->segment))
//             ->when($request->assigned_to, fn($q) => $q->where('assigned_to', $request->assigned_to))
//             ->when($request->has('is_active'), fn($q) =>
//                 $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
//             )
//             ->orderBy('name')
//             ->paginate($request->per_page ?? 20);

//         return $this->paginated($clients, ClientResource::class);
//     }

//     /**
//      * @OA\Post(
//      *   path="/marketing/clients",
//      *   tags={"Marketing"},
//      *   summary="Tambah klien baru",
//      *   security={{"bearerAuth":{}}},
//      *   @OA\RequestBody(required=true,
//      *     @OA\JsonContent(
//      *       required={"name"},
//      *       @OA\Property(property="type",        type="string", enum={"individual","company"}, default="company"),
//      *       @OA\Property(property="name",        type="string"),
//      *       @OA\Property(property="email",       type="string", format="email", nullable=true),
//      *       @OA\Property(property="phone",       type="string", nullable=true),
//      *       @OA\Property(property="address",     type="string", nullable=true),
//      *       @OA\Property(property="city",        type="string", nullable=true),
//      *       @OA\Property(property="npwp",        type="string", nullable=true),
//      *       @OA\Property(property="segment",     type="string", nullable=true),
//      *       @OA\Property(property="assigned_to", type="string", format="uuid", nullable=true)
//      *     )
//      *   ),
//      *   @OA\Response(response=201, description="Klien berhasil ditambahkan")
//      * )
//      */
//     public function store(Request $request): JsonResponse
//     {
//         $validated = $request->validate([
//             'type'        => ['sometimes', 'in:individual,company'],
//             'name'        => ['required', 'string', 'max:255'],
//             'alias'       => ['nullable', 'string', 'max:100'],
//             'email'       => ['nullable', 'email', 'max:255'],
//             'phone'       => ['nullable', 'string', 'max:30'],
//             'address'     => ['nullable', 'string', 'max:500'],
//             'city'        => ['nullable', 'string', 'max:100'],
//             'province'    => ['nullable', 'string', 'max:100'],
//             'postal_code' => ['nullable', 'string', 'max:10'],
//             'country'     => ['nullable', 'string', 'max:100'],
//             'npwp'        => ['nullable', 'string', 'max:30'],
//             'website'     => ['nullable', 'url', 'max:255'],
//             'segment'     => ['nullable', 'string', 'max:50'],
//             'tags'        => ['nullable', 'array'],
//             'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
//             'notes'       => ['nullable', 'string'],
//         ]);

//         $client = Client::create(array_merge($validated, [
//             'tenant_id' => $request->user()->tenant_id,
//             'is_active' => true,
//         ]));

//         return $this->created(
//             new ClientResource($client->load(['contacts', 'assignedTo'])),
//             'Klien berhasil ditambahkan.'
//         );
//     }

//     /**
//      * @OA\Get(
//      *   path="/marketing/clients/{id}",
//      *   tags={"Marketing"},
//      *   summary="Detail klien",
//      *   security={{"bearerAuth":{}}},
//      *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
//      *   @OA\Response(response=200, description="OK",
//      *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/ClientResource"))
//      *   )
//      * )
//      */
//     public function show(string $id): JsonResponse
//     {
//         $client = Client::with(['contacts', 'assignedTo', 'sourceLead'])->find($id);
//         if (! $client) return $this->notFound('Klien');
//         return $this->ok(new ClientResource($client));
//     }

//     /**
//      * @OA\Patch(
//      *   path="/marketing/clients/{id}",
//      *   tags={"Marketing"},
//      *   summary="Update data klien",
//      *   security={{"bearerAuth":{}}},
//      *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
//      *   @OA\Response(response=200, description="OK")
//      * )
//      */
//     public function update(Request $request, string $id): JsonResponse
//     {
//         $client = Client::find($id);
//         if (! $client) return $this->notFound('Klien');

//         $validated = $request->validate([
//             'name'        => ['sometimes', 'string', 'max:255'],
//             'alias'       => ['sometimes', 'nullable', 'string'],
//             'email'       => ['sometimes', 'nullable', 'email'],
//             'phone'       => ['sometimes', 'nullable', 'string', 'max:30'],
//             'address'     => ['sometimes', 'nullable', 'string'],
//             'city'        => ['sometimes', 'nullable', 'string'],
//             'npwp'        => ['sometimes', 'nullable', 'string'],
//             'segment'     => ['sometimes', 'nullable', 'string'],
//             'tags'        => ['sometimes', 'nullable', 'array'],
//             'assigned_to' => ['sometimes', 'nullable', 'uuid'],
//             'notes'       => ['sometimes', 'nullable', 'string'],
//             'is_active'   => ['sometimes', 'boolean'],
//         ]);

//         $client->update($validated);
//         return $this->ok(new ClientResource($client->load('contacts')), 'Data klien berhasil diperbarui.');
//     }

//     /**
//      * @OA\Delete(
//      *   path="/marketing/clients/{id}",
//      *   tags={"Marketing"},
//      *   summary="Nonaktifkan klien",
//      *   security={{"bearerAuth":{}}},
//      *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
//      *   @OA\Response(response=200, description="Klien dinonaktifkan")
//      * )
//      */
//     public function destroy(string $id): JsonResponse
//     {
//         $client = Client::find($id);
//         if (! $client) return $this->notFound('Klien');

//         $client->update(['is_active' => false]);
//         $client->delete();
//         return $this->ok(null, 'Klien berhasil dinonaktifkan.');
//     }

//     /**
//      * @OA\Get(
//      *   path="/marketing/clients/{id}/transactions",
//      *   tags={"Marketing"},
//      *   summary="Riwayat transaksi klien (quotation, invoice, proyek)",
//      *   security={{"bearerAuth":{}}},
//      *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
//      *   @OA\Response(response=200, description="OK",
//      *     @OA\JsonContent(
//      *       @OA\Property(property="data", type="object",
//      *         @OA\Property(property="summary",    type="object"),
//      *         @OA\Property(property="quotations", type="array", @OA\Items(type="object")),
//      *         @OA\Property(property="invoices",   type="array", @OA\Items(type="object")),
//      *         @OA\Property(property="projects",   type="array", @OA\Items(type="object"))
//      *       )
//      *     )
//      *   )
//      * )
//      */
//     public function transactions(string $id): JsonResponse
//     {
//         $client = Client::find($id);
//         if (! $client) return $this->notFound('Klien');

//         $invoices   = Invoice::where('client_id', $id)->latest('invoice_date')->limit(10)->get();
//         $quotations = Quotation::where('client_id', $id)->latest('date')->limit(10)->get();
//         $projects   = \App\Models\Project::where('client_id', $id)->latest()->limit(10)->get();

//         return $this->ok([
//             'summary' => [
//                 'total_quotations'     => Quotation::where('client_id', $id)->count(),
//                 'total_invoices'       => Invoice::where('client_id', $id)->count(),
//                 'total_revenue'        => (float) Invoice::where('client_id', $id)->where('status', 'paid')->sum('total_amount'),
//                 'outstanding_balance'  => (float) Invoice::where('client_id', $id)->whereNotIn('status', ['paid', 'cancelled', 'void'])->sum('remaining_amount'),
//                 'active_projects'      => \App\Models\Project::where('client_id', $id)->where('status', 'in_progress')->count(),
//             ],
//             'quotations' => $quotations->map(fn($q) => [
//                 'id'               => $q->id,
//                 'quotation_number' => $q->quotation_number,
//                 'date'             => $q->date?->toDateString(),
//                 'total_amount'     => (float) $q->total_amount,
//                 'status'           => $q->status,
//             ]),
//             'invoices' => $invoices->map(fn($inv) => [
//                 'id'             => $inv->id,
//                 'invoice_number' => $inv->invoice_number,
//                 'invoice_date'   => $inv->invoice_date?->toDateString(),
//                 'due_date'       => $inv->due_date?->toDateString(),
//                 'total_amount'   => (float) $inv->total_amount,
//                 'paid_amount'    => (float) $inv->paid_amount,
//                 'status'         => $inv->status,
//             ]),
//             'projects' => $projects->map(fn($p) => [
//                 'id'             => $p->id,
//                 'project_number' => $p->project_number,
//                 'name'           => $p->name,
//                 'status'         => $p->status,
//                 'progress'       => $p->progress_percent,
//             ]),
//         ]);
//     }
// }

// // ══════════════════════════════════════════════════════════
// // ClientContactController
// // ══════════════════════════════════════════════════════════
// class ClientContactController extends BaseController
// {
//     /** @OA\Get(path="/marketing/clients/{clientId}/contacts", tags={"Marketing"}, summary="Daftar contact person klien", security={{"bearerAuth":{}}},
//      *   @OA\Parameter(name="clientId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
//      *   @OA\Response(response=200, description="OK")
//      * )
//      */
//     public function index(string $clientId): JsonResponse
//     {
//         $contacts = ClientContact::where('client_id', $clientId)->orderByDesc('is_primary')->get();
//         return $this->ok($contacts->map(fn($c) => ['id'=>$c->id,'name'=>$c->name,'position'=>$c->position,'email'=>$c->email,'phone'=>$c->phone,'is_primary'=>$c->is_primary,'notes'=>$c->notes]));
//     }

//     /** @OA\Post(path="/marketing/clients/{clientId}/contacts", tags={"Marketing"}, summary="Tambah contact person", security={{"bearerAuth":{}}},
//      *   @OA\Parameter(name="clientId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
//      *   @OA\RequestBody(required=true, @OA\JsonContent(required={"name"}, @OA\Property(property="name",type="string"), @OA\Property(property="position",type="string",nullable=true), @OA\Property(property="email",type="string",nullable=true), @OA\Property(property="phone",type="string",nullable=true), @OA\Property(property="is_primary",type="boolean"))),
//      *   @OA\Response(response=201, description="Contact ditambahkan")
//      * )
//      */
//     public function store(Request $request, string $clientId): JsonResponse
//     {
//         $client = Client::find($clientId);
//         if (! $client) return $this->notFound('Klien');

//         $validated = $request->validate([
//             'name'       => ['required', 'string', 'max:255'],
//             'position'   => ['nullable', 'string', 'max:100'],
//             'email'      => ['nullable', 'email', 'max:255'],
//             'phone'      => ['nullable', 'string', 'max:30'],
//             'is_primary' => ['sometimes', 'boolean'],
//             'notes'      => ['nullable', 'string'],
//         ]);

//         // Jika set as primary, reset yang lain
//         if ($validated['is_primary'] ?? false) {
//             ClientContact::where('client_id', $clientId)->update(['is_primary' => false]);
//         }

//         $contact = $client->contacts()->create($validated);
//         return $this->created(['id'=>$contact->id,'name'=>$contact->name], 'Contact person berhasil ditambahkan.');
//     }

//     /** @OA\Delete(path="/marketing/clients/{clientId}/contacts/{id}", tags={"Marketing"}, summary="Hapus contact person",
//      *   security={{"bearerAuth":{}}},
//      *   @OA\Parameter(name="clientId",in="path",required=true,@OA\Schema(type="string",format="uuid")),
//      *   @OA\Parameter(name="id",in="path",required=true,@OA\Schema(type="string",format="uuid")),
//      *   @OA\Response(response=200, description="Contact dihapus")
//      * )
//      */
//     public function destroy(string $clientId, string $id): JsonResponse
//     {
//         $contact = ClientContact::where('client_id', $clientId)->find($id);
//         if (! $contact) return $this->notFound('Contact person');
//         $contact->delete();
//         return $this->ok(null, 'Contact person berhasil dihapus.');
//     }
// }
