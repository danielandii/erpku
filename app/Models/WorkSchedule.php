<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// WorkSchedule
// ══════════════════════════════════════════════════════════
class WorkSchedule extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'name', 'work_days', 'check_in_time',
        'check_out_time', 'grace_period_minutes',
        'break_duration_minutes', 'is_active',
    ];

    protected $casts = [
        'work_days' => 'array',
        'is_active' => 'boolean',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Cek apakah tanggal tertentu adalah hari kerja berdasarkan jadwal ini.
     * dayOfWeek: 0=Minggu, 1=Senin, ..., 6=Sabtu
     */
    public function isWorkDay(\Carbon\Carbon $date): bool
    {
        $dayOfWeek = $date->dayOfWeek === 0 ? 7 : $date->dayOfWeek;
        return in_array($dayOfWeek, $this->work_days);
    }

    /**
     * Hitung keterlambatan dalam menit dari jam clock-in aktual.
     */
    public function calculateLateMinutes(\Carbon\Carbon $checkIn): int
    {
        $scheduledIn = \Carbon\Carbon::parse(
            $checkIn->format('Y-m-d') . ' ' . $this->check_in_time
        );
        $deadline = $scheduledIn->copy()->addMinutes($this->grace_period_minutes);

        if ($checkIn->lte($deadline)) {
            return 0;
        }

        return (int) $checkIn->diffInMinutes($scheduledIn);
    }
}
