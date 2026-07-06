<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware CheckPermission
 *
 * Memvalidasi bahwa user yang login memiliki permission yang dibutuhkan.
 *
 * Usage di routes/api.php:
 *   Route::post('invoices', [InvoiceController::class, 'store'])
 *       ->middleware('permission:finance.invoices.create');
 *
 *   // Multiple permissions (any):
 *   Route::middleware('permission:finance.invoices.read|finance.reports.read')
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'code'    => 'UNAUTHENTICATED',
            ], 401);
        }

        // Super Admin bypass semua permission check
        if ($user->is_super_admin) {
            return $next($request);
        }

        // Cek apakah user memiliki salah satu dari permissions yang diperlukan
        $hasPermission = $user->hasAnyPermission($permissions);

        if (! $hasPermission) {
            return response()->json([
                'success'     => false,
                'message'     => 'Anda tidak memiliki izin untuk melakukan aksi ini.',
                'code'        => 'PERMISSION_DENIED',
                'required'    => $permissions,
            ], 403);
        }

        return $next($request);
    }
}
