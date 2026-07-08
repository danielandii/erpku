<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════
// LeaveType
// ══════════════════════════════════════════════════════════
class LeaveType extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'default_days',
        'is_paid', 'carry_over', 'max_carry_over_days',
        'requires_document', 'gender_specific',
        'max_consecutive_days', 'is_active',
    ];

    protected $casts = [
        'is_paid'            => 'boolean',
        'carry_over'         => 'boolean',
        'requires_document'  => 'boolean',
        'is_active'          => 'boolean',
    ];

    public function leaveAllocations()
    {
        return $this->hasMany(LeaveAllocation::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
