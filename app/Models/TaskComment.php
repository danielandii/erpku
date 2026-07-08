<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
