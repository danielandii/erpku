<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class TenantModuleConfig extends Model
{
    use HasUuid;

    protected $fillable = [
        'tenant_id', 'module_name', 'is_enabled',
        'config_json', 'enabled_at', 'enabled_by',
    ];

    protected $casts = [
        'is_enabled'  => 'boolean',
        'config_json' => 'array',
        'enabled_at'  => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function enabledByUser()
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }
}
