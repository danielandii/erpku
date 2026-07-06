<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasUuid, HasTenant, HasAuditLog, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'user_id', 'employee_number', 'full_name',
        'nik_ktp', 'npwp', 'birth_place', 'birth_date', 'gender',
        'marital_status', 'num_dependants', 'address', 'phone',
        'email_personal', 'department_id', 'position_id',
        'work_schedule_id', 'manager_id', 'join_date', 'resign_date',
        'employment_type', 'contract_end_date',
        'bank_name', 'bank_account', 'bank_account_name',
        'bpjs_kes_number', 'bpjs_tk_number', 'photo_url', 'is_active',
    ];

    protected $casts = [
        'birth_date'        => 'date',
        'join_date'         => 'date',
        'resign_date'       => 'date',
        'contract_end_date' => 'date',
        'is_active'         => 'boolean',
    ];

    protected array $auditExclude = [
        'bank_account', 'nik_ktp', 'npwp',
    ];

    // ── Relationships ──────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function workSchedule()
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveAllocations()
    {
        return $this->hasMany(LeaveAllocation::class);
    }

    public function salaryStructure()
    {
        return $this->hasOne(SalaryStructure::class)->latestOfMany('effective_date');
    }

    public function salaryStructures()
    {
        return $this->hasMany(SalaryStructure::class);
    }

    public function payrollItems()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function bonuses()
    {
        return $this->hasMany(EmployeeBonus::class);
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * Hitung masa kerja dalam tahun dan bulan.
     */
    public function getServiceDurationAttribute(): string
    {
        $start = $this->join_date;
        $end   = $this->resign_date ?? now()->toDate();
        $diff  = $start->diff($end);
        return "{$diff->y} tahun {$diff->m} bulan";
    }

    /**
     * Cek apakah karyawan masih aktif (kontrak belum berakhir).
     */
    public function isActiveEmployee(): bool
    {
        if (! $this->is_active) return false;
        if ($this->resign_date && $this->resign_date->isPast()) return false;
        if ($this->employment_type === 'contract'
            && $this->contract_end_date
            && $this->contract_end_date->isPast()) return false;
        return true;
    }

    /**
     * Ambil sisa cuti untuk jenis cuti tertentu di tahun berjalan.
     */
    public function getRemainingLeave(string $leaveTypeCode): float
    {
        $alloc = $this->leaveAllocations()
            ->whereHas('leaveType', fn($q) => $q->where('code', $leaveTypeCode))
            ->where('year', now()->year)
            ->first();

        if (! $alloc) return 0;

        return $alloc->allocated_days
            + $alloc->carry_over_days
            - $alloc->used_days
            - $alloc->pending_days;
    }
}
