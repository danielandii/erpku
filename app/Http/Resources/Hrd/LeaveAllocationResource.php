<?php

namespace App\Http\Resources\Hrd;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'employee_id'     => $this->employee_id,
            'employee_name'   => $this->whenLoaded('employee', fn() => $this->employee->full_name),
            'leave_type_id'   => $this->leave_type_id,
            'leave_type'      => $this->whenLoaded('leaveType', fn() => [
                'id'   => $this->leaveType->id,
                'name' => $this->leaveType->name,
                'code' => $this->leaveType->code,
            ]),
            'year'            => $this->year,
            'allocated_days'  => (float) $this->allocated_days,
            'carry_over_days' => (float) $this->carry_over_days,
            'used_days'       => (float) $this->used_days,
            'pending_days'    => (float) $this->pending_days,
            'remaining_days'  => (float) $this->remaining_days,
        ];
    }
}
