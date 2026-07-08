<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// Project
// ══════════════════════════════════════════════════════════
class Project extends Model
{
    use HasUuid, HasTenant, HasAuditLog, HasDocumentNumber, SoftDeletes;

    protected string $documentPrefix       = 'PRJ';
    protected string $documentNumberColumn = 'project_number';

    protected $fillable = [
        'tenant_id', 'project_number', 'name', 'description',
        'client_id', 'quotation_id', 'manager_id',
        'start_date', 'end_date', 'actual_start_date', 'actual_end_date',
        'budget', 'actual_cost', 'status', 'priority',
        'progress_percent', 'color', 'tags',
        'is_billable', 'created_by',
    ];

    protected $casts = [
        'start_date'         => 'date',
        'end_date'           => 'date',
        'actual_start_date'  => 'date',
        'actual_end_date'    => 'date',
        'budget'             => 'decimal:2',
        'actual_cost'        => 'decimal:2',
        'progress_percent'   => 'integer',
        'tags'               => 'array',
        'is_billable'        => 'boolean',
    ];

    const STATUS_NOT_STARTED = 'not_started';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_ON_HOLD     = 'on_hold';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_CANCELLED   = 'cancelled';

    const PRIORITY_LOW      = 'low';
    const PRIORITY_MEDIUM   = 'medium';
    const PRIORITY_HIGH     = 'high';
    const PRIORITY_CRITICAL = 'critical';

    // ── Relationships ──────────────────────────────────────

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function members()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function memberUsers()
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function stages()
    {
        return $this->hasMany(TaskStage::class)->orderBy('sequence');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * Recalculate progress_percent dari task yang selesai.
     * Dipanggil setiap kali status task berubah.
     */
    public function recalculateProgress(): void
    {
        $total = $this->tasks()->whereNull('parent_task_id')->count();

        if ($total === 0) {
            $this->update(['progress_percent' => 0]);
            return;
        }

        $done = $this->tasks()
            ->whereNull('parent_task_id')
            ->where('is_done', true)
            ->count();

        $this->update([
            'progress_percent' => (int) round(($done / $total) * 100),
        ]);
    }

    public function isBudgetWarning(): bool
    {
        if (! $this->budget || $this->budget <= 0) return false;
        return ($this->actual_cost / $this->budget) >= 0.8;
    }

    public function isOverBudget(): bool
    {
        if (! $this->budget || $this->budget <= 0) return false;
        return $this->actual_cost > $this->budget;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Default task stages saat proyek baru dibuat.
     */
    public function createDefaultStages(): void
    {
        $defaults = [
            ['name' => 'Backlog',     'color' => '#94A3B8', 'sequence' => 1, 'is_done_stage' => false],
            ['name' => 'Todo',        'color' => '#3B82F6', 'sequence' => 2, 'is_done_stage' => false],
            ['name' => 'In Progress', 'color' => '#F59E0B', 'sequence' => 3, 'is_done_stage' => false],
            ['name' => 'Review',      'color' => '#8B5CF6', 'sequence' => 4, 'is_done_stage' => false],
            ['name' => 'Done',        'color' => '#22C55E', 'sequence' => 5, 'is_done_stage' => true],
        ];

        foreach ($defaults as $stage) {
            $this->stages()->create($stage);
        }
    }
}
