<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @OA\Info(
 *   title="NexERP API",
 *   version="1.0.0",
 *   description="Sistem ERP Modular — Laravel 11 REST API",
 *   @OA\Contact(email="dev@majujaya.co.id"),
 *   @OA\License(name="Proprietary")
 * )
 *
 * @OA\Server(url=L5_SWAGGER_CONST_HOST, description="API Server")
 *
 * @OA\SecurityScheme(
 *   securityScheme="bearerAuth",
 *   type="http",
 *   scheme="bearer",
 *   bearerFormat="JWT"
 * )
 *
 * @OA\Tag(name="Auth",      description="Autentikasi & sesi pengguna")
 * @OA\Tag(name="User",      description="Manajemen user & RBAC")
 * @OA\Tag(name="HRD",       description="Modul Human Resource")
 * @OA\Tag(name="Marketing", description="Modul Marketing & Sales")
 * @OA\Tag(name="Finance",   description="Modul Keuangan")
 * @OA\Tag(name="Project",   description="Modul Manajemen Proyek")
 */
class BaseController extends Controller
{
    // ── Success responses ─────────────────────────────────────────────────────

    /**
     * 200 OK — data tunggal atau array sederhana.
     */
    protected function ok(mixed $data, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $this->resolveData($data),
        ], $status);
    }

    /**
     * 201 Created.
     */
    protected function created(mixed $data, string $message = 'Data berhasil dibuat.'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $this->resolveData($data),
        ], 201);
    }

    /**
     * 200 dengan pagination meta.
     * $result bisa berupa LengthAwarePaginator atau sudah di-paginate.
     * $resourceClass opsional — jika diisi, setiap item di-wrap dengan resource tersebut.
     */
    protected function paginated(mixed $result, ?string $resourceClass = null): JsonResponse
    {
        // Jika LengthAwarePaginator
        if ($result instanceof LengthAwarePaginator) {
            $items = $resourceClass
                ? $resourceClass::collection($result)->resolve()
                : $result->items();

            return response()->json([
                'success' => true,
                'message' => 'OK',
                'data'    => $items,
                'meta'    => [
                    'current_page' => $result->currentPage(),
                    'last_page'    => $result->lastPage(),
                    'per_page'     => $result->perPage(),
                    'total'        => $result->total(),
                    'from'         => $result->firstItem(),
                    'to'           => $result->lastItem(),
                ],
            ]);
        }

        // Jika sudah array / collection biasa (fallback)
        return $this->ok($result);
    }

    // ── Error responses ───────────────────────────────────────────────────────

    /**
     * Error generik dengan HTTP status code dan error code.
     */
    protected function error(
        string $message,
        int    $status = 400,
        string $code   = 'BAD_REQUEST',
        array  $errors = []
    ): JsonResponse {
        $body = [
            'success' => false,
            'message' => $message,
            'code'    => $code,
        ];
        if (! empty($errors)) {
            $body['errors'] = $errors;
        }
        return response()->json($body, $status);
    }

    /**
     * 404 Not Found.
     */
    protected function notFound(string $resource = 'Data'): JsonResponse
    {
        return $this->error("{$resource} tidak ditemukan.", 404, 'NOT_FOUND');
    }

    /**
     * 403 Forbidden.
     */
    protected function forbidden(string $message = 'Anda tidak memiliki akses ke resource ini.'): JsonResponse
    {
        return $this->error($message, 403, 'FORBIDDEN');
    }

    /**
     * 401 Unauthorized.
     */
    protected function unauthorized(string $message = 'Autentikasi diperlukan.'): JsonResponse
    {
        return $this->error($message, 401, 'UNAUTHORIZED');
    }

    /**
     * 422 Unprocessable Entity — validasi gagal.
     */
    protected function validationFailed(array $errors, string $message = 'Data tidak valid.'): JsonResponse
    {
        return $this->error($message, 422, 'VALIDATION_ERROR', $errors);
    }

    /**
     * 204 No Content (untuk DELETE yang tidak mengembalikan body).
     * Catatan: beberapa klien lebih suka 200 dengan body kosong.
     * Kita pakai 200 agar konsisten dengan format { success, message, data }.
     */
    protected function noContent(string $message = 'Data berhasil dihapus.'): JsonResponse
    {
        return $this->ok(null, $message);
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    /**
     * Resolve data — jika resource Laravel API, panggil ->resolve() / toArray().
     */
    private function resolveData(mixed $data): mixed
    {
        if ($data === null) return null;

        // JsonResource atau ResourceCollection
        if ($data instanceof \Illuminate\Http\Resources\Json\JsonResource) {
            return $data->resolve();
        }
        if ($data instanceof \Illuminate\Http\Resources\Json\ResourceCollection) {
            return $data->resolve();
        }

        return $data;
    }
}
