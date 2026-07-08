<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// LeadActivity
// ══════════════════════════════════════════════════════════
class LeadActivity extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'lead_id', 'user_id', 'activity_type',
        'summary', 'activity_date', 'next_action',
        'next_action_date', 'attachment_url',
    ];

    protected $casts = [
        'activity_date'    => 'datetime',
        'next_action_date' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
