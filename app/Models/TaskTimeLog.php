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

// ══════════════════════════════════════════════════════════
// ProjectMember
// ══════════════════════════════════════════════════════════
class ProjectMember extends Model
{
    use HasUuid;

    protected $fillable = [
        'project_id', 'user_id', 'role', 'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    const ROLE_MANAGER  = 'manager';
    const ROLE_LEAD     = 'lead';
    const ROLE_MEMBER   = 'member';
    const ROLE_OBSERVER = 'observer';

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// ══════════════════════════════════════════════════════════
// TaskStage
// ══════════════════════════════════════════════════════════
class TaskStage extends Model
{
    use HasUuid;

    protected $fillable = [
        'project_id', 'name', 'color', 'sequence',
        'is_done_stage', 'wip_limit',
    ];

    protected $casts = [
        'is_done_stage' => 'boolean',
        'sequence'      => 'integer',
        'wip_limit'     => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'stage_id')->orderBy('sequence');
    }

    /**
     * Cek apakah stage sudah mencapai WIP limit.
     */
    public function isAtWipLimit(): bool
    {
        if (! $this->wip_limit || $this->wip_limit === 0) return false;
        return $this->tasks()->where('is_done', false)->count() >= $this->wip_limit;
    }
}

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

// ══════════════════════════════════════════════════════════
// TaskAssignee
// ══════════════════════════════════════════════════════════
class TaskAssignee extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'task_id', 'user_id', 'assigned_by', 'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}

// ══════════════════════════════════════════════════════════
// TaskDependency
// ══════════════════════════════════════════════════════════
class TaskDependency extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'task_id', 'depends_on_task_id', 'dependency_type',
    ];

    // FS = Finish-to-Start | SS = Start-to-Start
    // FF = Finish-to-Finish | SF = Start-to-Finish
    const TYPE_FS = 'FS';
    const TYPE_SS = 'SS';
    const TYPE_FF = 'FF';
    const TYPE_SF = 'SF';

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function dependsOnTask()
    {
        return $this->belongsTo(Task::class, 'depends_on_task_id');
    }
}

// ══════════════════════════════════════════════════════════
// TaskComment
// ══════════════════════════════════════════════════════════
class TaskComment extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'task_id', 'user_id', 'content',
        'parent_comment_id', 'mentions', 'edited_at',
    ];

    protected $casts = [
        'mentions'  => 'array',
        'edited_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parentComment()
    {
        return $this->belongsTo(TaskComment::class, 'parent_comment_id');
    }

    public function replies()
    {
        return $this->hasMany(TaskComment::class, 'parent_comment_id')->latest();
    }

    public function mentionedUsers()
    {
        if (empty($this->mentions)) return collect();
        return User::whereIn('id', $this->mentions)->get();
    }

    public function isEdited(): bool
    {
        return ! is_null($this->edited_at);
    }
}

// ══════════════════════════════════════════════════════════
// TaskChecklist
// ══════════════════════════════════════════════════════════
class TaskChecklist extends Model
{
    use HasUuid;

    protected $fillable = [
        'task_id', 'title', 'is_done',
        'done_by', 'done_at', 'sequence',
    ];

    protected $casts = [
        'is_done'  => 'boolean',
        'done_at'  => 'datetime',
        'sequence' => 'integer',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function doneByUser()
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function toggleDone(User $user): void
    {
        $this->update([
            'is_done' => ! $this->is_done,
            'done_by' => $this->is_done ? null : $user->id,
            'done_at' => $this->is_done ? null : now(),
        ]);
    }
}

// ══════════════════════════════════════════════════════════
// TaskAttachment
// ══════════════════════════════════════════════════════════
class TaskAttachment extends Model
{
    use HasUuid;

    protected $fillable = [
        'task_id', 'uploaded_by', 'filename',
        'file_url', 'mime_type', 'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size ?? 0;
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1)    . ' KB';
        return $bytes . ' B';
    }
}

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
