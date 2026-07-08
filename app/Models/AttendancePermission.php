<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

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
