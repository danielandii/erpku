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
