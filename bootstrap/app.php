<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ── Global API Middleware ──────────────────────────────
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // ── Named (Route) Middleware ───────────────────────────
        $middleware->alias([
            'module'     => \App\Http\Middleware\ModuleGuard::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
        ]);

        // ── Throttle per route group ───────────────────────────
        $middleware->throttleWithRedis();

    })
    ->withExceptions(function (Exceptions $exceptions) {

        // Kembalikan JSON untuk semua exception di API routes
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {

                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sesi telah berakhir. Silakan login kembali.',
                        'code'    => 'UNAUTHENTICATED',
                    ], 401);
                }

                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data yang dikirim tidak valid.',
                        'code'    => 'VALIDATION_ERROR',
                        'errors'  => $e->errors(),
                    ], 422);
                }

                if ($e instanceof NotFoundHttpException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Resource tidak ditemukan.',
                        'code'    => 'NOT_FOUND',
                    ], 404);
                }

                if ($e instanceof MethodNotAllowedHttpException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'HTTP method tidak diizinkan.',
                        'code'    => 'METHOD_NOT_ALLOWED',
                    ], 405);
                }

                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    $model = class_basename($e->getModel());
                    return response()->json([
                        'success' => false,
                        'message' => "{$model} tidak ditemukan.",
                        'code'    => 'MODEL_NOT_FOUND',
                    ], 404);
                }

                if (config('app.debug')) {
                    return response()->json([
                        'success'   => false,
                        'message'   => $e->getMessage(),
                        'code'      => 'SERVER_ERROR',
                        'exception' => get_class($e),
                        'file'      => $e->getFile(),
                        'line'      => $e->getLine(),
                        'trace'     => collect($e->getTrace())->take(10),
                    ], 500);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan pada server. Silakan coba lagi.',
                    'code'    => 'SERVER_ERROR',
                ], 500);
            }
        });

    })->create();
