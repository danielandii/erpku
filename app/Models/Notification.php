<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

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
