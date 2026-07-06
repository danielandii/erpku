<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware ModuleGuard
 *
 * Memastikan modul yang diakses sudah diaktifkan untuk tenant yang bersangkutan.
 * Ditempatkan di route group per modul.
 *
 * Usage di routes/api.php:
 *   Route::middleware(['auth:sanctum', 'module:hrd'])->group(function () {
 *       Route::apiResource('employees', EmployeeController::class);
 *   });
 */
class ModuleGuard
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        // Super Admin bypass semua pengecekan modul
        if ($user?->is_super_admin) {
            return $next($request);
        }

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'code'    => 'UNAUTHENTICATED',
            ], 401);
        }

        $isEnabled = cache()->remember(
            "module_enabled_{$user->tenant_id}_{$module}",
            now()->addMinutes(30),
            fn() => \App\Models\TenantModuleConfig::where('tenant_id', $user->tenant_id)
                ->where('module_name', $module)
                ->where('is_enabled', true)
                ->exists()
        );

        if (! $isEnabled) {
            return response()->json([
                'success' => false,
                'message' => "Modul '{$module}' tidak aktif untuk tenant Anda.",
                'code'    => 'MODULE_DISABLED',
                'module'  => $module,
            ], 403);
        }

        return $next($request);
    }
}
