<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasUuid, HasAuditLog, Notifiable, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'employee_id', 'full_name', 'email',
        'password', 'avatar_url', 'phone', 'is_active',
        'is_super_admin', 'two_factor_enabled',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at'  => 'datetime',
        'locked_until'       => 'datetime',
        'last_login_at'      => 'datetime',
        'password_changed_at'=> 'datetime',
        'is_active'          => 'boolean',
        'is_super_admin'     => 'boolean',
        'two_factor_enabled' => 'boolean',
        'password'           => 'hashed',
    ];

    protected array $auditExclude = [
        'password', 'remember_token', 'two_factor_secret',
        'failed_login_count', 'locked_until',
    ];

    // ── Relationships ──────────────────────────────────────

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('assigned_by', 'assigned_at', 'expires_at')
            ->withTimestamps();
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function loginHistories()
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // ── Permission Helpers ─────────────────────────────────

    /**
     * Ambil semua permission dari semua role yang dimiliki user.
     * Hasil di-cache per request untuk performa.
     */
    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        return cache()->remember(
            "user_permissions_{$this->id}",
            now()->addMinutes(10),
            fn() => $this->roles()
                ->with('permissions')
                ->get()
                ->flatMap(fn($role) => $role->permissions->pluck('name'))
                ->unique()
        );
    }

    /**
     * Cek apakah user memiliki permission tertentu.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->getAllPermissions()->contains($permission);
    }

    /**
     * Cek apakah user memiliki salah satu dari beberapa permission.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        $userPerms = $this->getAllPermissions();
        return collect($permissions)->some(fn($p) => $userPerms->contains($p));
    }

    /**
     * Cek apakah akun dikunci sementara (brute force protection).
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Increment failed login counter, kunci akun jika >= 5 kali.
     */
    public function incrementFailedLogin(): void
    {
        $this->increment('failed_login_count');

        if ($this->failed_login_count >= 5) {
            $this->update(['locked_until' => now()->addMinutes(30)]);
        }
    }

    /**
     * Reset failed login counter setelah berhasil login.
     */
    public function resetFailedLogin(): void
    {
        $this->update([
            'failed_login_count' => 0,
            'locked_until'       => null,
            'last_login_at'      => now(),
        ]);
    }
}
