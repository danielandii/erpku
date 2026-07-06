<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════
// Setting
// ══════════════════════════════════════════════════════════
class Setting extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'key', 'value', 'type', 'description',
    ];

    /**
     * Ambil nilai setting dengan cast sesuai tipe.
     */
    public function getCastedValueAttribute(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'decimal' => (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode($this->value, true),
            default   => $this->value,
        };
    }

    /**
     * Ambil setting per key untuk tenant tertentu (dengan cache).
     */
    public static function getValue(string $tenantId, string $key, mixed $default = null): mixed
    {
        $setting = cache()->remember(
            "setting_{$tenantId}_{$key}",
            now()->addHour(),
            fn() => static::where('tenant_id', $tenantId)->where('key', $key)->first()
        );

        return $setting ? $setting->casted_value : $default;
    }

    /**
     * Set nilai setting (dengan flush cache).
     */
    public static function setValue(string $tenantId, string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => (string) $value]
        );

        cache()->forget("setting_{$tenantId}_{$key}");
    }
}

// ══════════════════════════════════════════════════════════
// Notification
// ══════════════════════════════════════════════════════════
class Notification extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'type',
        'title', 'body', 'data', 'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return ! is_null($this->read_at);
    }

    public function markAsRead(): void
    {
        if (! $this->isRead()) {
            $this->update(['read_at' => now()]);
        }
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}

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
