<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// TaskTimeLog
// ══════════════════════════════════════════════════════════
class TaskTimeLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'task_id', 'user_id', 'log_date',
        'hours', 'description', 'is_billable',
    ];

    protected $casts = [
        'log_date'    => 'date',
        'hours'       => 'decimal:2',
        'is_billable' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Update actual_hours di task setiap kali time log berubah
        static::saved(function ($log) {
            $log->task->recalculateActualHours();
        });

        static::deleted(function ($log) {
            $log->task->recalculateActualHours();
        });
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
