<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/notifications",
     *   tags={"Auth"},
     *   summary="Daftar notifikasi user yang login",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="unread_only", in="query", description="Hanya notifikasi yang belum dibaca", @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="per_page",    in="query", @OA\Schema(type="integer", default=20)),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="array",
     *         @OA\Items(
     *           @OA\Property(property="id",         type="string", format="uuid"),
     *           @OA\Property(property="type",       type="string"),
     *           @OA\Property(property="title",      type="string"),
     *           @OA\Property(property="body",       type="string", nullable=true),
     *           @OA\Property(property="data",       type="object", nullable=true),
     *           @OA\Property(property="is_read",    type="boolean"),
     *           @OA\Property(property="read_at",    type="string", format="date-time", nullable=true),
     *           @OA\Property(property="created_at", type="string", format="date-time")
     *         )
     *       ),
     *       @OA\Property(property="unread_count", type="integer")
     *     )
     *   )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $query = Notification::where('user_id', $userId)
            ->when($request->unread_only, fn($q) => $q->unread())
            ->latest();

        $paginator    = $query->paginate($request->per_page ?? 20);
        $unreadCount  = Notification::where('user_id', $userId)->unread()->count();

        return response()->json([
            'success'      => true,
            'message'      => 'OK',
            'data'         => $paginator->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'body'       => $n->body,
                'data'       => $n->data,
                'is_read'    => $n->isRead(),
                'read_at'    => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'unread_count' => $unreadCount,
            'meta'         => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * @OA\Patch(
     *   path="/notifications/{id}/read",
     *   tags={"Auth"},
     *   summary="Tandai notifikasi sebagai sudah dibaca",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)->find($id);
        if (! $notification) return $this->notFound('Notifikasi');

        $notification->markAsRead();

        return $this->ok([
            'id'      => $notification->id,
            'is_read' => true,
            'read_at' => $notification->read_at?->toIso8601String(),
        ], 'Notifikasi ditandai telah dibaca.');
    }

    /**
     * @OA\Post(
     *   path="/notifications/read-all",
     *   tags={"Auth"},
     *   summary="Tandai semua notifikasi sebagai sudah dibaca",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Semua notifikasi ditandai dibaca")
     * )
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return $this->ok(['marked_count' => $count], "{$count} notifikasi ditandai telah dibaca.");
    }
}
