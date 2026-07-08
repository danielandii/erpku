<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


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
