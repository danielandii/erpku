<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════
// AuditLog (read-only model)
// ══════════════════════════════════════════════════════════
class AuditLog extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'user_id', 'module', 'action',
        'resource_type', 'resource_id',
        'old_values', 'new_values', 'ip_address', 'notes',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
