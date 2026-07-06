<?php

namespace App\Http\Resources\Hrd;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendancePermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'employee_id'     => $this->employee_id,
            'employee_name'   => $this->whenLoaded('employee', fn() => $this->employee->full_name),
            'attendance_id'   => $this->attendance_id,
            'permission_date' => $this->permission_date?->toDateString() ?? $this->permission_date,
            'permission_type' => $this->permission_type,
            'reason'          => $this->reason,
            'document_url'    => $this->document_url,
            'status'          => $this->status,
            'rejection_notes' => $this->rejection_notes,
            'approved_by'     => $this->whenLoaded('approvedBy', fn() =>
                $this->approvedBy?->full_name
            ),
            'approved_at'     => $this->approved_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
