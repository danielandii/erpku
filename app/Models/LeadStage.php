<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// LeadStage
// ══════════════════════════════════════════════════════════
class LeadStage extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'name', 'sequence', 'color',
        'is_won', 'is_lost', 'is_active',
    ];

    protected $casts = [
        'is_won'    => 'boolean',
        'is_lost'   => 'boolean',
        'is_active' => 'boolean',
    ];

    public function leads()
    {
        return $this->hasMany(Lead::class, 'stage_id');
    }
}
