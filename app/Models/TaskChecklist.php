<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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