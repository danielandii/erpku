<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════
// Attendance
// ══════════════════════════════════════════════════════════
class Attendance extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'employee_id', 'attendance_date', 'status',
        'check_in_time', 'check_out_time',
        'check_in_lat', 'check_in_lng', 'check_out_lat', 'check_out_lng',
        'check_in_photo', 'check_out_photo',
        'late_minutes', 'work_duration_minutes', 'notes', 'approved_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in_time'  => 'datetime',
        'check_out_time' => 'datetime',
        'late_minutes'   => 'integer',
        'work_duration_minutes' => 'integer',
    ];

    // Status constants
    const STATUS_PRESENT    = 'present';
    const STATUS_LATE       = 'late';
    const STATUS_ABSENT     = 'absent';
    const STATUS_PERMISSION = 'permission';
    const STATUS_SICK       = 'sick';
    const STATUS_LEAVE      = 'leave';
    const STATUS_HOLIDAY    = 'holiday';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function permission()
    {
        return $this->hasOne(AttendancePermission::class);
    }

    /**
     * Hitung durasi kerja dari check-in dan check-out.
     */
    public function calculateWorkDuration(): ?int
    {
        if (! $this->check_in_time || ! $this->check_out_time) {
            return null;
        }
        return (int) $this->check_in_time->diffInMinutes($this->check_out_time);
    }

    public function isLate(): bool
    {
        return $this->late_minutes > 0;
    }
}

// ══════════════════════════════════════════════════════════
// AttendancePermission
// ══════════════════════════════════════════════════════════
class AttendancePermission extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'employee_id', 'attendance_id', 'permission_date',
        'permission_type', 'reason', 'document_url',
        'status', 'rejection_notes', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'permission_date' => 'date',
        'approved_at'     => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

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
