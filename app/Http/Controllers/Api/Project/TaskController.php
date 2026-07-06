<?php

namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Project\TaskResource;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\TaskStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/projects/{id}/tasks",
     *   tags={"Project"},
     *   summary="Daftar task proyek",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id",          in="path",  required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="stage_id",    in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="assignee_id", in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="is_done",     in="query", @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="is_overdue",  in="query", @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="priority",    in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="per_page",    in="query", @OA\Schema(type="integer", default=30)),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TaskResource")))
     *   )
     * )
     */
    public function indexByProject(Request $request, string $id): JsonResponse
    {
        $tasks = Task::with(['stage', 'assigneeUsers', 'checklists', 'subTasks'])
            ->where('project_id', $id)
            ->whereNull('parent_task_id')
            ->when($request->stage_id,    fn($q) => $q->where('stage_id', $request->stage_id))
            ->when($request->priority,    fn($q) => $q->where('priority', $request->priority))
            ->when($request->has('is_done'), fn($q) =>
                $q->where('is_done', filter_var($request->is_done, FILTER_VALIDATE_BOOLEAN))
            )
            ->when($request->assignee_id, fn($q) =>
                $q->whereHas('assignees', fn($q) => $q->where('user_id', $request->assignee_id))
            )
            ->when($request->is_overdue, fn($q) =>
                $q->where('due_date', '<', now())->where('is_done', false)
            )
            ->orderBy('sequence')
            ->paginate($request->per_page ?? 30);

        return $this->paginated($tasks, TaskResource::class);
    }

    /**
     * @OA\Post(
     *   path="/tasks",
     *   tags={"Project"},
     *   summary="Buat task baru",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"project_id","stage_id","title"},
     *       @OA\Property(property="project_id",       type="string", format="uuid"),
     *       @OA\Property(property="stage_id",         type="string", format="uuid"),
     *       @OA\Property(property="parent_task_id",   type="string", format="uuid", nullable=true),
     *       @OA\Property(property="title",            type="string"),
     *       @OA\Property(property="description",      type="string", nullable=true),
     *       @OA\Property(property="priority",         type="string", enum={"low","medium","high","critical"}, default="medium"),
     *       @OA\Property(property="start_date",       type="string", format="date", nullable=true),
     *       @OA\Property(property="due_date",         type="string", format="date", nullable=true),
     *       @OA\Property(property="estimated_hours",  type="number", nullable=true),
     *       @OA\Property(property="is_milestone",     type="boolean", default=false),
     *       @OA\Property(property="tags",             type="array", @OA\Items(type="string")),
     *       @OA\Property(property="assignee_ids",     type="array", @OA\Items(type="string", format="uuid"))
     *     )
     *   ),
     *   @OA\Response(response=201, description="Task berhasil dibuat",
     *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/TaskResource"))
     *   )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id'      => ['required', 'uuid', 'exists:projects,id'],
            'stage_id'        => ['required', 'uuid', 'exists:task_stages,id'],
            'parent_task_id'  => ['nullable', 'uuid', 'exists:tasks,id'],
            'title'           => ['required', 'string', 'max:500'],
            'description'     => ['nullable', 'string'],
            'priority'        => ['sometimes', 'in:low,medium,high,critical'],
            'start_date'      => ['nullable', 'date'],
            'due_date'        => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'is_milestone'    => ['sometimes', 'boolean'],
            'tags'            => ['nullable', 'array'],
            'tags.*'          => ['string', 'max:50'],
            'assignee_ids'    => ['nullable', 'array'],
            'assignee_ids.*'  => ['uuid', 'exists:users,id'],
        ]);

        // Cek WIP limit stage
        $stage = TaskStage::find($validated['stage_id']);
        if ($stage && $stage->isAtWipLimit()) {
            return $this->error(
                "Stage '{$stage->name}' sudah mencapai WIP limit ({$stage->wip_limit} task). Selesaikan task yang ada terlebih dahulu.",
                422, 'WIP_LIMIT_REACHED'
            );
        }

        // Tentukan sequence (taruh di akhir stage)
        $lastSeq = Task::where('stage_id', $validated['stage_id'])->max('sequence') ?? 0;

        $task = Task::create(array_merge($validated, [
            'tenant_id'  => $request->user()->tenant_id,
            'sequence'   => $lastSeq + 1000,
            'created_by' => $request->user()->id,
        ]));

        // Assign ke user
        if (! empty($validated['assignee_ids'])) {
            foreach (array_unique($validated['assignee_ids']) as $uid) {
                $task->assignees()->create([
                    'user_id'     => $uid,
                    'assigned_by' => $request->user()->id,
                    'assigned_at' => now(),
                ]);
            }
        }

        return $this->created(
            new TaskResource($task->load(['stage', 'assigneeUsers', 'checklists'])),
            'Task berhasil dibuat.'
        );
    }

    /**
     * @OA\Get(
     *   path="/tasks/{id}",
     *   tags={"Project"},
     *   summary="Detail task",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/TaskResource"))
     *   )
     * )
     */
    public function show(string $id): JsonResponse
    {
        $task = Task::with([
            'stage', 'project', 'parentTask', 'subTasks',
            'assigneeUsers', 'checklists', 'attachments',
            'timeLogs.user', 'dependsOn', 'createdBy',
        ])->find($id);

        if (! $task) return $this->notFound('Task');
        return $this->ok(new TaskResource($task));
    }

    /**
     * @OA\Patch(
     *   path="/tasks/{id}",
     *   tags={"Project"},
     *   summary="Update task",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $task = Task::find($id);
        if (! $task) return $this->notFound('Task');

        $validated = $request->validate([
            'title'            => ['sometimes', 'string', 'max:500'],
            'description'      => ['sometimes', 'nullable', 'string'],
            'priority'         => ['sometimes', 'in:low,medium,high,critical'],
            'start_date'       => ['sometimes', 'nullable', 'date'],
            'due_date'         => ['sometimes', 'nullable', 'date'],
            'estimated_hours'  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'progress_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'is_milestone'     => ['sometimes', 'boolean'],
            'tags'             => ['sometimes', 'nullable', 'array'],
        ]);

        $task->update($validated);

        // Auto-mark done jika progress 100%
        if (($validated['progress_percent'] ?? 0) === 100 && ! $task->is_done) {
            $task->markAsDone();
        }

        return $this->ok(
            new TaskResource($task->load(['stage', 'assigneeUsers'])),
            'Task berhasil diperbarui.'
        );
    }

    /**
     * @OA\Delete(
     *   path="/tasks/{id}",
     *   tags={"Project"},
     *   summary="Hapus task",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Task dihapus")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $task = Task::find($id);
        if (! $task) return $this->notFound('Task');
        $project = $task->project;
        $task->delete();
        $project?->recalculateProgress();
        return $this->ok(null, 'Task berhasil dihapus.');
    }

    /**
     * @OA\Post(
     *   path="/tasks/{id}/move",
     *   tags={"Project"},
     *   summary="Pindahkan task ke stage / posisi lain (Drag & Drop Kanban)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"stage_id"},
     *       @OA\Property(property="stage_id",     type="string", format="uuid", description="Stage tujuan"),
     *       @OA\Property(property="after_task_id",type="string", format="uuid", nullable=true, description="Taruh setelah task ini (null = paling atas)")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Task berhasil dipindahkan")
     * )
     */
    public function move(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'stage_id'      => ['required', 'uuid', 'exists:task_stages,id'],
            'after_task_id' => ['nullable', 'uuid', 'exists:tasks,id'],
        ]);

        $task = Task::find($id);
        if (! $task) return $this->notFound('Task');

        $targetStage = TaskStage::find($request->stage_id);

        // Cek WIP limit
        if ($task->stage_id !== $request->stage_id && $targetStage->isAtWipLimit()) {
            return $this->error(
                "Stage '{$targetStage->name}' sudah mencapai WIP limit.",
                422, 'WIP_LIMIT_REACHED'
            );
        }

        // Hitung sequence baru
        $afterTask = $request->after_task_id ? Task::find($request->after_task_id) : null;
        $newSeq    = $afterTask ? $afterTask->sequence + 500 : 0;

        $task->update([
            'stage_id' => $request->stage_id,
            'sequence' => $newSeq,
            'is_done'  => $targetStage->is_done_stage,
            'done_at'  => $targetStage->is_done_stage ? now() : null,
        ]);

        $task->project?->recalculateProgress();

        return $this->ok(
            ['stage_id' => $task->stage_id, 'sequence' => $task->sequence],
            'Task berhasil dipindahkan.'
        );
    }

    /**
     * @OA\Post(
     *   path="/tasks/{id}/done",
     *   tags={"Project"},
     *   summary="Tandai task sebagai selesai",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Task selesai")
     * )
     */
    public function markDone(string $id): JsonResponse
    {
        $task = Task::find($id);
        if (! $task) return $this->notFound('Task');

        if ($task->is_done) {
            return $this->error('Task sudah ditandai selesai.', 409, 'ALREADY_DONE');
        }

        $task->markAsDone();
        return $this->ok(new TaskResource($task->load(['stage'])), 'Task berhasil diselesaikan!');
    }
}
