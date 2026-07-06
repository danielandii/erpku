<?php

namespace App\Http\Resources\Hrd;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'period_year'      => $this->period_year,
            'period_month'     => $this->period_month,
            'period_label'     => $this->period_label,
            'status'           => $this->status,
            'total_gross'      => $this->total_gross ? (float) $this->total_gross : null,
            'total_deductions' => $this->total_deductions ? (float) $this->total_deductions : null,
            'total_net'        => $this->total_net ? (float) $this->total_net : null,
            'employee_count'   => $this->employee_count,
            'notes'            => $this->notes,
            'processed_at'     => $this->processed_at?->toIso8601String(),
            'finalized_at'     => $this->finalized_at?->toIso8601String(),
            'finalized_by'     => $this->whenLoaded('finalizedBy', fn() =>
                $this->finalizedBy?->full_name
            ),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
