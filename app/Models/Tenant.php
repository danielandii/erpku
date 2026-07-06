<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'logo_url', 'address', 'phone',
        'email', 'npwp', 'timezone', 'currency',
        'is_active', 'subscription_plan',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function moduleConfigs()
    {
        return $this->hasMany(TenantModuleConfig::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * Cek apakah modul tertentu aktif untuk tenant ini.
     */
    public function isModuleEnabled(string $moduleName): bool
    {
        return $this->moduleConfigs()
            ->where('module_name', $moduleName)
            ->where('is_enabled', true)
            ->exists();
    }

    /**
     * Ambil semua modul yang aktif.
     */
    public function enabledModules(): array
    {
        return $this->moduleConfigs()
            ->where('is_enabled', true)
            ->pluck('module_name')
            ->toArray();
    }
}
