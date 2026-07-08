<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════
// LeaveRequest
// ══════════════════════════════════════════════════════════
class LeaveRequest extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'employee_id', 'leave_type_id',
        'start_date', 'end_date', 'total_days', 'reason',
        'document_url', 'status', 'rejection_notes',
        'approved_by_l1', 'approved_at_l1',
        'approved_by_l2', 'approved_at_l2',
    ];

    protected $casts = [
        'start_date'     => 'date',
        'end_date'       => 'date',
        'total_days'     => 'decimal:1',
        'approved_at_l1' => 'datetime',
        'approved_at_l2' => 'datetime',
    ];

    const STATUS_DRAFT    = 'draft';
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED= 'cancelled';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approverL1()
    {
        return $this->belongsTo(User::class, 'approved_by_l1');
    }

    public function approverL2()
    {
        return $this->belongsTo(User::class, 'approved_by_l2');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
