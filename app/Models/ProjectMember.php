<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
