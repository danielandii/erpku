<?php

namespace App\Http\Resources\Marketing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// ══════════════════════════════════════════════════════════
/**
 * @OA\Schema(
 *   schema="LeadResource",
 *   @OA\Property(property="id",                  type="string", format="uuid"),
 *   @OA\Property(property="lead_number",          type="string", example="LDS-2024-0001"),
 *   @OA\Property(property="contact_name",         type="string"),
 *   @OA\Property(property="company_name",         type="string", nullable=true),
 *   @OA\Property(property="email",                type="string", nullable=true),
 *   @OA\Property(property="phone",                type="string", nullable=true),
 *   @OA\Property(property="source",               type="string", nullable=true),
 *   @OA\Property(property="expected_revenue",     type="number", nullable=true),
 *   @OA\Property(property="probability",          type="integer", nullable=true),
 *   @OA\Property(property="status",               type="string", enum={"active","on_hold","won","lost"}),
 *   @OA\Property(property="score",                type="integer"),
 *   @OA\Property(property="stage",                type="object"),
 *   @OA\Property(property="assigned_to",          type="object", nullable=true),
 *   @OA\Property(property="last_activity_at",     type="string", format="date-time", nullable=true),
 *   @OA\Property(property="next_followup_at",     type="string", format="date-time", nullable=true),
 *   @OA\Property(property="is_followup_overdue",  type="boolean"),
 *   @OA\Property(property="tags",                 type="array", @OA\Items(type="string"))
 * )
 */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'lead_number'        => $this->lead_number,
            'contact_name'       => $this->contact_name,
            'company_name'       => $this->company_name,
            'email'              => $this->email,
            'phone'              => $this->phone,
            'website'            => $this->website,
            'source'             => $this->source,
            'expected_revenue'   => $this->expected_revenue ? (float) $this->expected_revenue : null,
            'probability'        => $this->probability,
            'expected_close_date'=> $this->expected_close_date?->toDateString(),
            'status'             => $this->status,
            'score'              => $this->score,
            'lost_reason'        => $this->lost_reason,
            'lost_at'            => $this->lost_at?->toIso8601String(),
            'won_at'             => $this->won_at?->toIso8601String(),
            'tags'               => $this->tags ?? [],
            'notes'              => $this->notes,

            'stage'              => $this->whenLoaded('stage', fn() => [
                'id'      => $this->stage->id,
                'name'    => $this->stage->name,
                'color'   => $this->stage->color,
                'is_won'  => $this->stage->is_won,
                'is_lost' => $this->stage->is_lost,
            ]),

            'assigned_to'        => $this->whenLoaded('assignedTo', fn() =>
                $this->assignedTo ? [
                    'id'         => $this->assignedTo->id,
                    'full_name'  => $this->assignedTo->full_name,
                    'avatar_url' => $this->assignedTo->avatar_url,
                ] : null
            ),

            'last_activity_at'    => $this->last_activity_at?->toIso8601String(),
            'next_followup_at'    => $this->next_followup_at?->toIso8601String(),
            'is_followup_overdue' => $this->next_followup_at
                && $this->next_followup_at->isPast()
                && $this->status === 'active',

            'last_activity'       => $this->whenLoaded('activities', fn() =>
                $this->activities->first() ? [
                    'type'    => $this->activities->first()->activity_type,
                    'summary' => \Illuminate\Support\Str::limit($this->activities->first()->summary, 80),
                    'date'    => $this->activities->first()->activity_date?->toIso8601String(),
                ] : null
            ),

            'converted_client_id' => $this->converted_to_client_id,
            'is_converted'        => $this->isConverted(),

            'quotations_count'    => $this->whenLoaded('quotations', fn() => $this->quotations->count()),

            'created_by'          => $this->whenLoaded('createdBy', fn() =>
                $this->createdBy?->full_name
            ),
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}

// ══════════════════════════════════════════════════════════
class LeadActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'activity_type'    => $this->activity_type,
            'summary'          => $this->summary,
            'activity_date'    => $this->activity_date?->toIso8601String(),
            'next_action'      => $this->next_action,
            'next_action_date' => $this->next_action_date?->toIso8601String(),
            'attachment_url'   => $this->attachment_url,
            'user'             => $this->whenLoaded('user', fn() => [
                'id'        => $this->user->id,
                'full_name' => $this->user->full_name,
            ]),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}

// ══════════════════════════════════════════════════════════
/**
 * @OA\Schema(
 *   schema="QuotationResource",
 *   @OA\Property(property="id",               type="string", format="uuid"),
 *   @OA\Property(property="quotation_number", type="string", example="QUO-2024-0001"),
 *   @OA\Property(property="version",          type="integer"),
 *   @OA\Property(property="client_name",      type="string"),
 *   @OA\Property(property="date",             type="string", format="date"),
 *   @OA\Property(property="valid_until",      type="string", format="date", nullable=true),
 *   @OA\Property(property="subtotal",         type="number"),
 *   @OA\Property(property="discount_amount",  type="number"),
 *   @OA\Property(property="tax_amount",       type="number"),
 *   @OA\Property(property="total_amount",     type="number"),
 *   @OA\Property(property="status",           type="string", enum={"draft","sent","confirmed","invoiced","expired","cancelled"}),
 *   @OA\Property(property="is_expired",       type="boolean"),
 *   @OA\Property(property="items",            type="array", @OA\Items(type="object"))
 * )
 */
class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'quotation_number' => $this->quotation_number,
            'version'          => $this->version,
            'parent_id'        => $this->parent_id,
            'lead_id'          => $this->lead_id,
            'client_id'        => $this->client_id,
            'client_name'      => $this->client_name_snapshot,
            'client_address'   => $this->client_address_snapshot,
            'date'             => $this->date?->toDateString(),
            'valid_until'      => $this->valid_until?->toDateString(),
            'currency'         => $this->currency,
            'subtotal'         => (float) $this->subtotal,
            'discount_percent' => $this->discount_percent ? (float) $this->discount_percent : null,
            'discount_amount'  => (float) $this->discount_amount,
            'tax_amount'       => (float) $this->tax_amount,
            'total_amount'     => (float) $this->total_amount,
            'status'           => $this->status,
            'is_expired'       => $this->isExpired(),
            'can_be_invoiced'  => $this->canBeInvoiced(),
            'po_number'        => $this->po_number,
            'terms_conditions' => $this->terms_conditions,
            'notes'            => $this->notes,
            'sent_at'          => $this->sent_at?->toIso8601String(),
            'confirmed_at'     => $this->confirmed_at?->toIso8601String(),

            'items'            => $this->whenLoaded('items', fn() =>
                $this->items->map(fn($item) => [
                    'id'               => $item->id,
                    'sequence'         => $item->sequence,
                    'description'      => $item->description,
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

            'pic'              => $this->whenLoaded('pic', fn() =>
                $this->pic ? ['id' => $this->pic->id, 'full_name' => $this->pic->full_name] : null
            ),
            'created_by'       => $this->whenLoaded('createdBy', fn() => $this->createdBy?->full_name),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}

// ══════════════════════════════════════════════════════════
/**
 * @OA\Schema(
 *   schema="ClientResource",
 *   @OA\Property(property="id",            type="string", format="uuid"),
 *   @OA\Property(property="client_number", type="string", example="CLI-2024-0001"),
 *   @OA\Property(property="type",          type="string", enum={"individual","company"}),
 *   @OA\Property(property="name",          type="string"),
 *   @OA\Property(property="email",         type="string", nullable=true),
 *   @OA\Property(property="phone",         type="string", nullable=true),
 *   @OA\Property(property="segment",       type="string", nullable=true),
 *   @OA\Property(property="is_active",     type="boolean"),
 *   @OA\Property(property="total_revenue", type="number"),
 *   @OA\Property(property="contacts",      type="array", @OA\Items(type="object"))
 * )
 */
class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'client_number' => $this->client_number,
            'type'          => $this->type,
            'name'          => $this->name,
            'alias'         => $this->alias,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'address'       => $this->address,
            'city'          => $this->city,
            'province'      => $this->province,
            'postal_code'   => $this->postal_code,
            'country'       => $this->country,
            'npwp'          => $this->npwp,
            'website'       => $this->website,
            'segment'       => $this->segment,
            'tags'          => $this->tags ?? [],
            'notes'         => $this->notes,
            'is_active'     => $this->is_active,
            'total_revenue' => (float) $this->total_revenue,

            'contacts'      => $this->whenLoaded('contacts', fn() =>
                $this->contacts->map(fn($c) => [
                    'id'         => $c->id,
                    'name'       => $c->name,
                    'position'   => $c->position,
                    'email'      => $c->email,
                    'phone'      => $c->phone,
                    'is_primary' => $c->is_primary,
                ])
            ),

            'assigned_to'   => $this->whenLoaded('assignedTo', fn() =>
                $this->assignedTo ? [
                    'id'        => $this->assignedTo->id,
                    'full_name' => $this->assignedTo->full_name,
                ] : null
            ),

            'source_lead_id'=> $this->source_lead_id,
            'created_at'    => $this->created_at?->toIso8601String(),
        ];
    }
}
