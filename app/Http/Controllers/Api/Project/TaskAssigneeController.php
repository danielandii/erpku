<?php

namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Api\BaseController;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\TaskChecklist;
use App\Models\TaskComment;
use App\Models\TaskDependency;
use App\Models\TaskTimeLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// ══════════════════════════════════════════════════════════
// TaskAssigneeController
// ══════════════════════════════════════════════════════════
class TaskAssigneeController extends BaseController
{
    /**
     * @OA\Post(
     *   path="/tasks/{id}/assignees",
     *   tags={"Project"},
     *   summary="Assign user ke task",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"user_id"},
     *       @OA\Property(property="user_id", type="string", format="uuid")
     *     )
     *   ),
     *   @OA\Response(response=201, description="User berhasil di-assign")
     * )
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $request->validate(['user_id' => ['required', 'uuid', 'exists:users,id']]);

        $task = Task::find($id);
        if (! $task) return $this->notFound('Task');

        $exists = TaskAssignee::where('task_id', $id)->where('user_id', $request->user_id)->exists();
        if ($exists) {
            return $this->error('User sudah di-assign ke task ini.', 409, 'ALREADY_ASSIGNED');
        }

        $assignee = TaskAssignee::create([
            'task_id'     => $id,
            'user_id'     => $request->user_id,
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
        ]);

        return $this->created(['id' => $assignee->id], 'User berhasil di-assign ke task.');
    }

    /**
     * @OA\Delete(
     *   path="/tasks/{id}/assignees/{userId}",
     *   tags={"Project"},
     *   summary="Hapus assignee dari task",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id",     in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Assignee dihapus")
     * )
     */
    public function destroy(string $id, string $userId): JsonResponse
    {
        $deleted = TaskAssignee::where('task_id', $id)->where('user_id', $userId)->delete();
        if (! $deleted) return $this->notFound('Assignee');
        return $this->ok(null, 'Assignee berhasil dihapus dari task.');
    }
}

// ══════════════════════════════════════════════════════════
// TaskDependencyController
// ══════════════════════════════════════════════════════════
class TaskDependencyController extends BaseController
{
    /**
     * @OA\Post(
     *   path="/tasks/{id}/dependencies",
     *   tags={"Project"},
     *   summary="Tambah dependensi task",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"depends_on_task_id"},
     *       @OA\Property(property="depends_on_task_id", type="string", format="uuid"),
     *       @OA\Property(property="dependency_type",    type="string", enum={"FS","SS","FF","SF"}, default="FS")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Dependensi berhasil ditambahkan"),
     *   @OA\Response(response=422, description="Circular dependency terdeteksi")
     * )
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'depends_on_task_id' => ['required', 'uuid', 'exists:tasks,id', 'different:' . $id],
            'dependency_type'    => ['sometimes', 'in:FS,SS,FF,SF'],
        ]);

        $task       = Task::find($id);
        $dependsOn  = Task::find($request->depends_on_task_id);

        if (! $task || ! $dependsOn) return $this->notFound('Task');

        // Cek apakah task berasal dari proyek yang sama
        if ($task->project_id !== $dependsOn->project_id) {
            return $this->error('Dependensi hanya bisa dibuat antar task dalam proyek yang sama.', 422, 'DIFFERENT_PROJECT');
        }

        // Deteksi circular dependency
        if ($task->wouldCreateCircularDependency($dependsOn)) {
            return $this->error(
                'Tidak dapat menambahkan dependensi ini karena akan membuat circular dependency.',
                422, 'CIRCULAR_DEPENDENCY'
            );
        }

        $dep = TaskDependency::firstOrCreate(
            ['task_id' => $id, 'depends_on_task_id' => $request->depends_on_task_id],
            ['dependency_type' => $request->dependency_type ?? 'FS']
        );

        return $this->created([
            'id'                 => $dep->id,
            'depends_on_task_id' => $dep->depends_on_task_id,
            'dependency_type'    => $dep->dependency_type,
        ], 'Dependensi berhasil ditambahkan.');
    }

    /**
     * @OA\Delete(
     *   path="/tasks/{id}/dependencies/{depId}",
     *   tags={"Project"},
     *   summary="Hapus dependensi task",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id",    in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="depId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Dependensi dihapus")
     * )
     */
    public function destroy(string $id, string $depId): JsonResponse
    {
        $deleted = TaskDependency::where('task_id', $id)->where('id', $depId)->delete();
        if (! $deleted) return $this->notFound('Dependensi');
        return $this->ok(null, 'Dependensi berhasil dihapus.');
    }
}

// ══════════════════════════════════════════════════════════
// TaskCommentController
// ══════════════════════════════════════════════════════════
class TaskCommentController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/tasks/{id}/comments",
     *   tags={"Project"},
     *   summary="Daftar komentar task",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(string $id): JsonResponse
    {
        $task = Task::find($id);
        if (! $task) return $this->notFound('Task');

        $comments = $task->comments()->with(['user', 'replies.user'])->paginate(30);

        return $this->paginated($comments, \App\Http\Resources\Project\TaskCommentResource::class);
    }

    /**
     * @OA\Post(
     *   path="/tasks/{id}/comments",
     *   tags={"Project"},
     *   summary="Tambah komentar ke task",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"content"},
     *       @OA\Property(property="content",           type="string", description="Mendukung Markdown & @mention"),
     *       @OA\Property(property="parent_comment_id", type="string", format="uuid", nullable=true),
     *       @OA\Property(property="mentions",          type="array", @OA\Items(type="string", format="uuid"))
     *     )
     *   ),
     *   @OA\Response(response=201, description="Komentar berhasil ditambahkan")
     * )
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $task = Task::find($id);
        if (! $task) return $this->notFound('Task');

        $validated = $request->validate([
            'content'           => ['required', 'string', 'max:5000'],
            'parent_comment_id' => ['nullable', 'uuid', 'exists:task_comments,id'],
            'mentions'          => ['nullable', 'array'],
            'mentions.*'        => ['uuid', 'exists:users,id'],
        ]);

        $comment = TaskComment::create(array_merge($validated, [
            'task_id' => $id,
            'user_id' => $request->user()->id,
        ]));

        // TODO: Dispatch notifications ke mentioned users
        // if (!empty($validated['mentions'])) {
        //     \App\Jobs\NotifyMentionedUsersJob::dispatch($comment);
        // }

        return $this->created(
            new \App\Http\Resources\Project\TaskCommentResource($comment->load('user')),
            'Komentar berhasil ditambahkan.'
        );
    }

    /**
     * @OA\Patch(
     *   path="/tasks/comments/{commentId}",
     *   tags={"Project"},
     *   summary="Edit komentar",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="commentId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(required={"content"}, @OA\Property(property="content", type="string"))
     *   ),
     *   @OA\Response(response=200, description="Komentar diperbarui")
     * )
     */
    public function update(Request $request, string $commentId): JsonResponse
    {
        $comment = TaskComment::find($commentId);
        if (! $comment) return $this->notFound('Komentar');

        if ($comment->user_id !== $request->user()->id) {
            return $this->forbidden('Anda hanya bisa mengedit komentar milik sendiri.');
        }

        $request->validate(['content' => ['required', 'string', 'max:5000']]);
        $comment->update(['content' => $request->content, 'edited_at' => now()]);

        return $this->ok(
            new \App\Http\Resources\Project\TaskCommentResource($comment->load('user')),
            'Komentar berhasil diperbarui.'
        );
    }

    /**
     * @OA\Delete(
     *   path="/tasks/comments/{commentId}",
     *   tags={"Project"},
     *   summary="Hapus komentar",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="commentId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Komentar dihapus")
     * )
     */
    public function destroy(Request $request, string $commentId): JsonResponse
    {
        $comment = TaskComment::find($commentId);
        if (! $comment) return $this->notFound('Komentar');

        $isOwner  = $comment->user_id === $request->user()->id;
        $isAdmin  = $request->user()->is_super_admin || $request->user()->hasPermission('project.tasks.delete');

        if (! $isOwner && ! $isAdmin) {
            return $this->forbidden('Anda tidak memiliki izin untuk menghapus komentar ini.');
        }

        $comment->delete();
        return $this->ok(null, 'Komentar berhasil dihapus.');
    }
}

// ══════════════════════════════════════════════════════════
// TaskChecklistController
// ══════════════════════════════════════════════════════════
class TaskChecklistController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/tasks/{id}/checklists",
     *   tags={"Project"},
     *   summary="Daftar checklist task",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(string $id): JsonResponse
    {
        $task = Task::find($id);
        if (! $task) return $this->notFound('Task');

        return $this->ok($task->checklists()->orderBy('sequence')->get()->map(fn($c) => [
            'id'        => $c->id,
            'title'     => $c->title,
            'is_done'   => $c->is_done,
            'done_at'   => $c->done_at?->toIso8601String(),
            'sequence'  => $c->sequence,
        ]));
    }

    /**
     * @OA\Post(
     *   path="/tasks/{id}/checklists",
     *   tags={"Project"},
     *   summary="Tambah item checklist",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"title"},
     *       @OA\Property(property="title",   type="string"),
     *       @OA\Property(property="sequence",type="integer", nullable=true)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Checklist item berhasil ditambahkan")
     * )
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $task = Task::find($id);
        if (! $task) return $this->notFound('Task');

        $request->validate([
            'title'    => ['required', 'string', 'max:500'],
            'sequence' => ['nullable', 'integer', 'min:0'],
        ]);

        $lastSeq = $task->checklists()->max('sequence') ?? 0;

        $item = TaskChecklist::create([
            'task_id'  => $id,
            'title'    => $request->title,
            'sequence' => $request->sequence ?? $lastSeq + 10,
        ]);

        return $this->created([
            'id'       => $item->id,
            'title'    => $item->title,
            'is_done'  => $item->is_done,
            'sequence' => $item->sequence,
        ], 'Checklist item berhasil ditambahkan.');
    }

    /**
     * @OA\Patch(
     *   path="/tasks/checklists/{checkId}",
     *   tags={"Project"},
     *   summary="Update checklist item",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="checkId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(@OA\JsonContent(@OA\Property(property="title", type="string"), @OA\Property(property="is_done", type="boolean"))),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function update(Request $request, string $checkId): JsonResponse
    {
        $item = TaskChecklist::find($checkId);
        if (! $item) return $this->notFound('Checklist item');

        $request->validate([
            'title'    => ['sometimes', 'string', 'max:500'],
            'is_done'  => ['sometimes', 'boolean'],
            'sequence' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($request->has('is_done')) {
            $item->update([
                'is_done' => $request->is_done,
                'done_by' => $request->is_done ? $request->user()->id : null,
                'done_at' => $request->is_done ? now() : null,
            ]);
        }

        if ($request->has('title'))    $item->update(['title'    => $request->title]);
        if ($request->has('sequence')) $item->update(['sequence' => $request->sequence]);

        return $this->ok(['id' => $item->id, 'is_done' => $item->is_done, 'title' => $item->title], 'Checklist item diperbarui.');
    }

    /**
     * @OA\Delete(
     *   path="/tasks/checklists/{checkId}",
     *   tags={"Project"},
     *   summary="Hapus checklist item",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="checkId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Item dihapus")
     * )
     */
    public function destroy(string $checkId): JsonResponse
    {
        $item = TaskChecklist::find($checkId);
        if (! $item) return $this->notFound('Checklist item');
        $item->delete();
        return $this->ok(null, 'Checklist item berhasil dihapus.');
    }

    /**
     * @OA\Post(
     *   path="/tasks/checklists/{checkId}/toggle",
     *   tags={"Project"},
     *   summary="Toggle status done checklist item",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="checkId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Status berhasil diubah")
     * )
     */
    public function toggle(Request $request, string $checkId): JsonResponse
    {
        $item = TaskChecklist::find($checkId);
        if (! $item) return $this->notFound('Checklist item');

        $item->toggleDone($request->user());
        return $this->ok([
            'id'      => $item->id,
            'is_done' => $item->is_done,
            'done_at' => $item->done_at?->toIso8601String(),
        ], $item->is_done ? 'Item ditandai selesai.' : 'Item ditandai belum selesai.');
    }
}

// ══════════════════════════════════════════════════════════
// TaskTimeLogController
// ══════════════════════════════════════════════════════════
class TaskTimeLogController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/tasks/{id}/time-logs",
     *   tags={"Project"},
     *   summary="Log waktu kerja task",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(string $id): JsonResponse
    {
        $task = Task::find($id);
        if (! $task) return $this->notFound('Task');

        $logs = $task->timeLogs()->with('user')->paginate(20);

        return $this->paginated($logs, \App\Http\Resources\Project\TaskTimeLogResource::class);
    }

    /**
     * @OA\Post(
     *   path="/tasks/{id}/time-logs",
     *   tags={"Project"},
     *   summary="Catat waktu kerja (time tracking)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"log_date","hours"},
     *       @OA\Property(property="log_date",    type="string", format="date"),
     *       @OA\Property(property="hours",       type="number", example=2.5, description="Jumlah jam (0.5 = 30 menit)"),
     *       @OA\Property(property="description", type="string", nullable=true),
     *       @OA\Property(property="is_billable", type="boolean", default=true)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Waktu berhasil dicatat")
     * )
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $task = Task::find($id);
        if (! $task) return $this->notFound('Task');

        $validated = $request->validate([
            'log_date'    => ['required', 'date', 'before_or_equal:today'],
            'hours'       => ['required', 'numeric', 'min:0.1', 'max:24'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_billable' => ['sometimes', 'boolean'],
        ]);

        $log = TaskTimeLog::create(array_merge($validated, [
            'task_id' => $id,
            'user_id' => $request->user()->id,
        ]));

        return $this->created([
            'id'          => $log->id,
            'hours'       => (float) $log->hours,
            'log_date'    => $log->log_date?->toDateString(),
            'description' => $log->description,
            'task_total_hours' => (float) $task->fresh()->actual_hours,
        ], "Waktu kerja {$log->hours} jam berhasil dicatat.");
    }

    /**
     * @OA\Patch(
     *   path="/tasks/time-logs/{logId}",
     *   tags={"Project"},
     *   summary="Update time log",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="logId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function update(Request $request, string $logId): JsonResponse
    {
        $log = TaskTimeLog::find($logId);
        if (! $log) return $this->notFound('Time log');

        if ($log->user_id !== $request->user()->id && ! $request->user()->is_super_admin) {
            return $this->forbidden('Anda hanya bisa mengedit time log milik sendiri.');
        }

        $validated = $request->validate([
            'log_date'    => ['sometimes', 'date'],
            'hours'       => ['sometimes', 'numeric', 'min:0.1', 'max:24'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_billable' => ['sometimes', 'boolean'],
        ]);

        $log->update($validated);
        return $this->ok(['id' => $log->id, 'hours' => (float) $log->hours], 'Time log berhasil diperbarui.');
    }

    /**
     * @OA\Delete(
     *   path="/tasks/time-logs/{logId}",
     *   tags={"Project"},
     *   summary="Hapus time log",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="logId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Time log dihapus")
     * )
     */
    public function destroy(Request $request, string $logId): JsonResponse
    {
        $log = TaskTimeLog::find($logId);
        if (! $log) return $this->notFound('Time log');

        if ($log->user_id !== $request->user()->id && ! $request->user()->is_super_admin) {
            return $this->forbidden('Anda hanya bisa menghapus time log milik sendiri.');
        }

        $log->delete();
        return $this->ok(null, 'Time log berhasil dihapus.');
    }
}

// ══════════════════════════════════════════════════════════
// TaskAttachmentController
// ══════════════════════════════════════════════════════════
class TaskAttachmentController extends BaseController
{
    /**
     * @OA\Post(
     *   path="/tasks/{id}/attachments",
     *   tags={"Project"},
     *   summary="Upload lampiran ke task",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"filename","file_url"},
     *       @OA\Property(property="filename",  type="string"),
     *       @OA\Property(property="file_url",  type="string", description="URL dari storage setelah upload"),
     *       @OA\Property(property="mime_type", type="string"),
     *       @OA\Property(property="file_size", type="integer")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Lampiran berhasil ditambahkan")
     * )
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $task = Task::find($id);
        if (! $task) return $this->notFound('Task');

        $validated = $request->validate([
            'filename'  => ['required', 'string', 'max:255'],
            'file_url'  => ['required', 'string', 'max:500'],
            'mime_type' => ['nullable', 'string', 'max:100'],
            'file_size' => ['nullable', 'integer', 'min:0'],
        ]);

        $attachment = $task->attachments()->create(array_merge($validated, [
            'uploaded_by' => $request->user()->id,
        ]));

        return $this->created([
            'id'             => $attachment->id,
            'filename'       => $attachment->filename,
            'file_url'       => $attachment->file_url,
            'file_size_human'=> $attachment->file_size_human,
        ], 'Lampiran berhasil ditambahkan.');
    }

    /**
     * @OA\Delete(
     *   path="/tasks/attachments/{attId}",
     *   tags={"Project"},
     *   summary="Hapus lampiran task",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="attId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Lampiran dihapus")
     * )
     */
    public function destroy(Request $request, string $attId): JsonResponse
    {
        $att = \App\Models\TaskAttachment::find($attId);
        if (! $att) return $this->notFound('Lampiran');

        if ($att->uploaded_by !== $request->user()->id && ! $request->user()->is_super_admin) {
            return $this->forbidden('Anda hanya bisa menghapus lampiran yang Anda upload.');
        }

        // TODO: Delete from storage
        $att->delete();
        return $this->ok(null, 'Lampiran berhasil dihapus.');
    }
}
