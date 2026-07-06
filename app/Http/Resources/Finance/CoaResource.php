<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// ══════════════════════════════════════════════════════════
class CoaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'name'           => $this->name,
            'account_type'   => $this->account_type,
            'normal_balance' => $this->normal_balance,
            'level'          => $this->level,
            'is_detail'      => $this->is_detail,
            'is_cash_account'=> $this->is_cash_account,
            'is_active'      => $this->is_active,
            'description'    => $this->description,
            'parent_id'      => $this->parent_id,
            'parent'         => $this->whenLoaded('parent', fn() =>
                $this->parent ? ['id' => $this->parent->id, 'code' => $this->parent->code, 'name' => $this->parent->name] : null
            ),
            'children'       => $this->whenLoaded('children', fn() =>
                CoaResource::collection($this->children)
            ),
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}

// ══════════════════════════════════════════════════════════
class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'bill_number'           => $this->bill_number,
            'vendor_name'           => $this->vendor_name,
            'vendor_invoice_number' => $this->vendor_invoice_number,
            'bill_date'             => $this->bill_date?->toDateString(),
            'due_date'              => $this->due_date?->toDateString(),
            'description'           => $this->description,
            'amount'                => (float) $this->amount,
            'paid_amount'           => (float) $this->paid_amount,
            'remaining_amount'      => (float) $this->remaining_amount,
            'status'                => $this->status,
            'is_overdue'            => $this->status !== 'paid'
                && $this->status !== 'cancelled'
                && \Carbon\Carbon::parse($this->due_date)->isPast(),
            'attachment_url'        => $this->attachment_url,
            'notes'                 => $this->notes,
            'approved_at'           => $this->approved_at?->toIso8601String(),
            'coa'                   => $this->whenLoaded('coa', fn() =>
                $this->coa ? ['code' => $this->coa->code, 'name' => $this->coa->name] : null
            ),
            'approved_by'           => $this->whenLoaded('approvedBy', fn() =>
                $this->approvedBy?->full_name
            ),
            'created_by'            => $this->whenLoaded('createdBy', fn() =>
                $this->createdBy?->full_name
            ),
            'created_at'            => $this->created_at?->toIso8601String(),
        ];
    }
}

// ══════════════════════════════════════════════════════════
class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'bank_name'       => $this->bank_name,
            'account_number'  => $this->account_number,
            'account_name'    => $this->account_name,
            'account_type'    => $this->account_type,
            'currency'        => $this->currency,
            'current_balance' => (float) $this->current_balance,
            'branch'          => $this->branch,
            'is_active'       => $this->is_active,
            'is_default'      => $this->is_default,
            'coa'             => $this->whenLoaded('coa', fn() =>
                $this->coa ? ['code' => $this->coa->code, 'name' => $this->coa->name] : null
            ),
        ];
    }
}
