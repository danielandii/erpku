<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════
// LeaveAllocation
// ══════════════════════════════════════════════════════════
class LeaveAllocation extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'employee_id', 'leave_type_id', 'year',
        'allocated_days', 'used_days', 'pending_days', 'carry_over_days',
    ];

    protected $casts = [
        'allocated_days'   => 'decimal:1',
        'used_days'        => 'decimal:1',
        'pending_days'     => 'decimal:1',
        'carry_over_days'  => 'decimal:1',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function getRemainingDaysAttribute(): float
    {
        return $this->allocated_days
            + $this->carry_over_days
            - $this->used_days
            - $this->pending_days;
    }
}
