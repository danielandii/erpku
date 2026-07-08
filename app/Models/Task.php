<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// Task
// ══════════════════════════════════════════════════════════
class Task extends Model
{
    use HasUuid, HasTenant, HasAuditLog, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'project_id', 'stage_id', 'parent_task_id',
        'title', 'description', 'priority',
        'start_date', 'due_date', 'estimated_hours', 'actual_hours',
        'progress_percent', 'is_milestone', 'is_done', 'done_at',
        'tags', 'created_by', 'sequence',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'due_date'         => 'date',
        'done_at'          => 'datetime',
        'estimated_hours'  => 'decimal:2',
        'actual_hours'     => 'decimal:2',
        'progress_percent' => 'integer',
        'is_milestone'     => 'boolean',
        'is_done'          => 'boolean',
        'tags'             => 'array',
        'sequence'         => 'integer',
    ];

    const PRIORITY_LOW      = 'low';
    const PRIORITY_MEDIUM   = 'medium';
    const PRIORITY_HIGH     = 'high';
    const PRIORITY_CRITICAL = 'critical';

    // ── Relationships ──────────────────────────────────────

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function stage()
    {
        return $this->belongsTo(TaskStage::class);
    }

    public function parentTask()
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subTasks()
    {
        return $this->hasMany(Task::class, 'parent_task_id')->orderBy('sequence');
    }

    public function assignees()
    {
        return $this->hasMany(TaskAssignee::class);
    }

    public function assigneeUsers()
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot('assigned_by', 'assigned_at');
    }

    public function dependencies()
    {
        return $this->hasMany(TaskDependency::class);
    }

    public function dependsOn()
    {
        return $this->belongsToMany(
            Task::class, 'task_dependencies',
            'task_id', 'depends_on_task_id'
        )->withPivot('dependency_type');
    }

    public function dependents()
    {
        return $this->belongsToMany(
            Task::class, 'task_dependencies',
            'depends_on_task_id', 'task_id'
        )->withPivot('dependency_type');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class)->whereNull('parent_comment_id')->latest();
    }

    public function checklists()
    {
        return $this->hasMany(TaskChecklist::class)->orderBy('sequence');
    }

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class)->latest();
    }

    public function timeLogs()
    {
        return $this->hasMany(TaskTimeLog::class)->latest('log_date');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * Tandai task sebagai selesai dan pindahkan ke done stage.
     */
    public function markAsDone(): void
    {
        $doneStage = $this->project->stages()
            ->where('is_done_stage', true)
            ->first();

        $this->update([
            'is_done'          => true,
            'done_at'          => now(),
            'progress_percent' => 100,
            'stage_id'         => $doneStage?->id ?? $this->stage_id,
        ]);

        // Recalculate progress project
        $this->project->recalculateProgress();
    }

    /**
     * Recalculate actual_hours dari semua time log.
     */
    public function recalculateActualHours(): void
    {
        $total = $this->timeLogs()->sum('hours');
        $this->update(['actual_hours' => $total]);
    }

    /**
     * Hitung % checklist yang selesai.
     */
    public function getChecklistProgressAttribute(): int
    {
        $total = $this->checklists()->count();
        if ($total === 0) return 0;
        $done = $this->checklists()->where('is_done', true)->count();
        return (int) round(($done / $total) * 100);
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && ! $this->is_done;
    }

    /**
     * Deteksi circular dependency sebelum menambahkan dependensi baru.
     */
    public function wouldCreateCircularDependency(Task $dependsOnTask): bool
    {
        // BFS untuk cek apakah dependsOnTask depends on $this (circular)
        $visited = [];
        $queue   = [$dependsOnTask->id];

        while (! empty($queue)) {
            $currentId = array_shift($queue);

            if ($currentId === $this->id) return true;
            if (in_array($currentId, $visited)) continue;

            $visited[] = $currentId;

            $nextIds = TaskDependency::where('task_id', $currentId)
                ->pluck('depends_on_task_id')
                ->toArray();

            $queue = array_merge($queue, $nextIds);
        }

        return false;
    }
}
