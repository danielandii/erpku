<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Trait HasTenant
 *
 * Menambahkan global scope tenant_id secara otomatis ke semua query.
 * Setiap model yang menggunakan trait ini akan secara otomatis memfilter
 * data berdasarkan tenant_id dari user yang sedang login.
 *
 * PENTING: Pastikan tabel model memiliki kolom `tenant_id`.
 *
 * Usage:
 *   class Invoice extends Model {
 *     use HasUuid, HasTenant;
 *   }
 *
 * Untuk bypass scope (misal di seeder atau Super Admin):
 *   Invoice::withoutTenantScope()->get();
 */
trait HasTenant
{
    protected static function bootHasTenant(): void
    {
        // Otomatis set tenant_id saat creating
        static::creating(function ($model) {
            if (empty($model->tenant_id) && Auth::check()) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });

        // Tambahkan global scope untuk filtering
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::check() && ! Auth::user()->is_super_admin) {
                $builder->where(
                    $builder->getModel()->getTable() . '.tenant_id',
                    Auth::user()->tenant_id
                );
            }
        });
    }

    /**
     * Bypass tenant scope — digunakan oleh Super Admin atau proses background.
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope('tenant');
    }

    /**
     * Relasi ke Tenant.
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
