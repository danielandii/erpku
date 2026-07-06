<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// ══════════════════════════════════════════════════════════
/**
 * @OA\Schema(
 *   schema="InvoiceResource",
 *   @OA\Property(property="id",              type="string", format="uuid"),
 *   @OA\Property(property="invoice_number",  type="string", example="INV-2024-0001"),
 *   @OA\Property(property="type",            type="string", enum={"invoice","credit_note"}),
 *   @OA\Property(property="client_name",     type="string"),
 *   @OA\Property(property="invoice_date",    type="string", format="date"),
 *   @OA\Property(property="due_date",        type="string", format="date"),
 *   @OA\Property(property="subtotal",        type="number"),
 *   @OA\Property(property="ppn_amount",      type="number"),
 *   @OA\Property(property="total_amount",    type="number"),
 *   @OA\Property(property="paid_amount",     type="number"),
 *   @OA\Property(property="remaining_amount",type="number"),
 *   @OA\Property(property="status",          type="string"),
 *   @OA\Property(property="is_overdue",      type="boolean"),
 *   @OA\Property(property="days_overdue",    type="integer", nullable=true),
 *   @OA\Property(property="items",           type="array", @OA\Items(type="object"))
 * )
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOverdue = $this->isOverdue();

        return [
            'id'               => $this->id,
            'invoice_number'   => $this->invoice_number,
            'type'             => $this->type,
            'parent_invoice_id'=> $this->parent_invoice_id,
            'quotation_id'     => $this->quotation_id,

            // Client snapshot
            'client_id'        => $this->client_id,
            'client_name'      => $this->client_name_snapshot,
            'client_address'   => $this->client_address_snapshot,
            'client_npwp'      => $this->client_npwp_snapshot,

            // Dates
            'invoice_date'     => $this->invoice_date?->toDateString(),
            'due_date'         => $this->due_date?->toDateString(),
            'payment_terms'    => $this->payment_terms,
            'currency'         => $this->currency,

            // Amounts
            'subtotal'         => (float) $this->subtotal,
            'discount_amount'  => (float) $this->discount_amount,
            'tax_base'         => (float) $this->tax_base,
            'ppn_amount'       => (float) $this->ppn_amount,
            'pph_amount'       => (float) $this->pph_amount,
            'total_amount'     => (float) $this->total_amount,
            'paid_amount'      => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,

            // Status
            'status'           => $this->status,
            'is_overdue'       => $isOverdue,
            'days_overdue'     => $isOverdue
                ? (int) now()->diffInDays($this->due_date)
                : null,
            'is_recurring'     => $this->is_recurring,
            'recur_interval'   => $this->recur_interval,

            // Text fields
            'notes'            => $this->notes,
            'internal_notes'   => $this->internal_notes,
            'terms_conditions' => $this->terms_conditions,
            'attachment_url'   => $this->attachment_url,

            // Items
            'items'            => $this->whenLoaded('items', fn() =>
                $this->items->map(fn($item) => [
                    'id'               => $item->id,
                    'sequence'         => $item->sequence,
                    'description'      => $item->description,
                    'coa_code'         => $item->coa?->code,
                    'quantity'         => (float) $item->quantity,
                    'unit'             => $item->unit,
                    'unit_price'       => (float) $item->unit_price,
                    'discount_percent' => $item->discount_percent ? (float) $item->discount_percent : null,
                    'tax_percent'      => $item->tax_percent ? (float) $item->tax_percent : null,
                    'subtotal'         => (float) $item->subtotal,
                    'discount_amount'  => (float) $item->discount_amount,
                    'tax_amount'       => (float) $item->tax_amount,
                    'total'            => (float) $item->total,
                ])
            ),

            // Payments
            'payments'         => $this->whenLoaded('payments', fn() =>
                $this->payments->map(fn($p) => [
                    'id'               => $p->id,
                    'payment_number'   => $p->payment_number,
                    'payment_date'     => $p->payment_date?->toDateString(),
                    'amount'           => (float) $p->amount,
                    'payment_method'   => $p->payment_method,
                    'reference_number' => $p->reference_number,
                ])
            ),

            // Relations
            'client'           => $this->whenLoaded('client', fn() =>
                $this->client ? ['id' => $this->client->id, 'name' => $this->client->name] : null
            ),
            'created_by'       => $this->whenLoaded('createdBy', fn() =>
                $this->createdBy?->full_name
            ),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}

// ══════════════════════════════════════════════════════════
/**
 * @OA\Schema(
 *   schema="PaymentResource",
 *   @OA\Property(property="id",               type="string", format="uuid"),
 *   @OA\Property(property="payment_number",   type="string", example="PAY-2024-0001"),
 *   @OA\Property(property="type",             type="string", enum={"inbound","outbound"}),
 *   @OA\Property(property="payment_date",     type="string", format="date"),
 *   @OA\Property(property="amount",           type="number"),
 *   @OA\Property(property="payment_method",   type="string"),
 *   @OA\Property(property="reference_number", type="string", nullable=true),
 *   @OA\Property(property="invoice_number",   type="string", nullable=true),
 *   @OA\Property(property="client_name",      type="string", nullable=true),
 *   @OA\Property(property="bank_account",     type="object", nullable=true),
 *   @OA\Property(property="journal_entry_id", type="string", format="uuid", nullable=true)
 * )
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'payment_number'   => $this->payment_number,
            'type'             => $this->type,
            'invoice_id'       => $this->invoice_id,
            'bill_id'          => $this->bill_id,
            'client_id'        => $this->client_id,
            'vendor_name'      => $this->vendor_name,
            'payment_date'     => $this->payment_date?->toDateString(),
            'amount'           => (float) $this->amount,
            'payment_method'   => $this->payment_method,
            'reference_number' => $this->reference_number,
            'notes'            => $this->notes,
            'attachment_url'   => $this->attachment_url,
            'journal_entry_id' => $this->journal_entry_id,

            'invoice'          => $this->whenLoaded('invoice', fn() =>
                $this->invoice ? [
                    'id'             => $this->invoice->id,
                    'invoice_number' => $this->invoice->invoice_number,
                    'total_amount'   => (float) $this->invoice->total_amount,
                    'status'         => $this->invoice->status,
                ] : null
            ),

            'client'           => $this->whenLoaded('client', fn() =>
                $this->client ? ['id' => $this->client->id, 'name' => $this->client->name] : null
            ),

            'bank_account'     => $this->whenLoaded('bankAccount', fn() =>
                $this->bankAccount ? [
                    'id'             => $this->bankAccount->id,
                    'bank_name'      => $this->bankAccount->bank_name,
                    'account_number' => $this->bankAccount->account_number,
                    'account_name'   => $this->bankAccount->account_name,
                ] : null
            ),

            'created_by'       => $this->whenLoaded('createdBy', fn() => $this->createdBy?->full_name),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}

// ══════════════════════════════════════════════════════════
class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'entry_number'   => $this->entry_number,
            'type'           => $this->type,
            'description'    => $this->description,
            'entry_date'     => $this->entry_date?->toDateString(),
            'period_year'    => $this->period_year,
            'period_month'   => $this->period_month,
            'total_debit'    => (float) $this->total_debit,
            'total_credit'   => (float) $this->total_credit,
            'is_balanced'    => $this->isBalanced(),
            'is_posted'      => $this->is_posted,
            'posted_at'      => $this->posted_at?->toIso8601String(),
            'is_reversed'    => $this->is_reversed,

            'lines'          => $this->whenLoaded('lines', fn() =>
                $this->lines->map(fn($line) => [
                    'id'           => $line->id,
                    'coa_code'     => $line->coa?->code,
                    'coa_name'     => $line->coa?->name,
                    'description'  => $line->description,
                    'debit_amount' => (float) $line->debit_amount,
                    'credit_amount'=> (float) $line->credit_amount,
                    'sequence'     => $line->sequence,
                ])
            ),

            'posted_by'      => $this->whenLoaded('postedBy', fn() => $this->postedBy?->full_name),
            'created_by'     => $this->whenLoaded('createdBy', fn() => $this->createdBy?->full_name),
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
