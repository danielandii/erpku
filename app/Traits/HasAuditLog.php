<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * Trait HasAuditLog
 *
 * Otomatis mencatat perubahan data ke tabel audit_logs.
 * Rekam: siapa yang melakukan aksi, kapan, nilai sebelum & sesudah.
 *
 * Usage:
 *   class Invoice extends Model {
 *     use HasAuditLog;
 *
 *     // Kolom yang TIDAK dicatat di audit log (sensitif / tidak penting)
 *     protected array $auditExclude = ['updated_at'];
 *   }
 */
trait HasAuditLog
{
    protected static function bootHasAuditLog(): void
    {
        static::created(function ($model) {
            static::writeAuditLog('CREATE', $model, [], $model->getAttributes());
        });

        static::updated(function ($model) {
            $old = $model->getOriginal();
            $new = $model->getChanges();

            // Hapus kolom yang dikecualikan
            $exclude = array_merge(
                ['updated_at', 'created_at'],
                $model->auditExclude ?? []
            );

            foreach ($exclude as $col) {
                unset($old[$col], $new[$col]);
            }

            if (! empty($new)) {
                // Filter old values hanya untuk kolom yang berubah
                $oldFiltered = array_intersect_key($old, $new);
                static::writeAuditLog('UPDATE', $model, $oldFiltered, $new);
            }
        });

        static::deleted(function ($model) {
            $isSoftDelete = method_exists($model, 'isForceDeleting')
                ? ! $model->isForceDeleting()
                : false;

            static::writeAuditLog(
                $isSoftDelete ? 'SOFT_DELETE' : 'DELETE',
                $model,
                $model->getOriginal(),
                []
            );
        });
    }

    private static function writeAuditLog(
        string $action,
        $model,
        array $oldValues,
        array $newValues
    ): void {
        // Jangan catat jika bukan dalam context HTTP request (misal seeder)
        if (! app()->runningInConsole() === false && app()->runningInConsole()) {
            return;
        }

        try {
            \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                'id'            => Str::uuid(),
                'tenant_id'     => $model->tenant_id ?? null,
                'user_id'       => Auth::id(),
                'module'        => $model->auditModule ?? static::guessModule(get_class($model)),
                'action'        => $action,
                'resource_type' => class_basename(get_class($model)),
                'resource_id'   => $model->getKey(),
                'old_values'    => empty($oldValues) ? null : json_encode($oldValues),
                'new_values'    => empty($newValues) ? null : json_encode($newValues),
                'ip_address'    => Request::ip(),
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            // Jangan sampai audit log error mengganggu operasi utama
            \Illuminate\Support\Facades\Log::warning('Audit log failed: ' . $e->getMessage());
        }
    }

    private static function guessModule(string $class): string
    {
        $map = [
            'User' => 'user', 'Role' => 'user', 'Permission' => 'user',
            'Employee' => 'hrd', 'Attendance' => 'hrd', 'LeaveRequest' => 'hrd',
            'PayrollPeriod' => 'hrd', 'PayrollItem' => 'hrd',
            'Lead' => 'marketing', 'Client' => 'marketing', 'Quotation' => 'marketing',
            'Invoice' => 'finance', 'Payment' => 'finance', 'Bill' => 'finance',
            'JournalEntry' => 'finance', 'ChartOfAccount' => 'finance',
            'Project' => 'project', 'Task' => 'project',
        ];

        $basename = class_basename($class);

        return $map[$basename] ?? 'system';
    }
}
