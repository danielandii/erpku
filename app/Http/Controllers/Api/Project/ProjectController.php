<?php

namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Project\ProjectResource;
use App\Http\Resources\Project\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/projects",
     *   tags={"Project"},
     *   summary="Daftar proyek",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="search",    in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="status",    in="query", @OA\Schema(type="string", enum={"not_started","in_progress","on_hold","completed","cancelled"})),
     *   @OA\Parameter(name="priority",  in="query", @OA\Schema(type="string", enum={"low","medium","high","critical"})),
     *   @OA\Parameter(name="client_id", in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="manager_id",in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="my_projects",in="query", description="Hanya proyek saya (sebagai member / manager)", @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="per_page",  in="query", @OA\Schema(type="integer", default=20)),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ProjectResource")))
     *   )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $projects = Project::with(['client', 'manager'])
            ->withCount(['tasks', 'tasks as done_tasks_count' => fn($q) => $q->where('is_done', true)])
            ->when($request->search, fn($q) =>
                $q->where(fn($q) =>
                    $q->where('name', 'like', "%{$request->search}%")
                      ->orWhere('project_number', 'like', "%{$request->search}%")
                )
            )
            ->when($request->status,    fn($q) => $q->where('status', $request->status))
            ->when($request->priority,  fn($q) => $q->where('priority', $request->priority))
            ->when($request->client_id, fn($q) => $q->where('client_id', $request->client_id))
            ->when($request->manager_id,fn($q) => $q->where('manager_id', $request->manager_id))
            ->when($request->my_projects, fn($q) =>
                $q->where(fn($q) =>
                    $q->where('manager_id', $userId)
                      ->orWhereHas('members', fn($q) => $q->where('user_id', $userId))
                )
            )
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($projects, ProjectResource::class);
    }

    /**
     * @OA\Post(
     *   path="/projects",
     *   tags={"Project"},
     *   summary="Buat proyek baru",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"name","manager_id"},
     *       @OA\Property(property="name",         type="string"),
     *       @OA\Property(property="description",  type="string", nullable=true),
     *       @OA\Property(property="client_id",    type="string", format="uuid", nullable=true),
     *       @OA\Property(property="quotation_id", type="string", format="uuid", nullable=true),
     *       @OA\Property(property="manager_id",   type="string", format="uuid"),
     *       @OA\Property(property="start_date",   type="string", format="date", nullable=true),
     *       @OA\Property(property="end_date",     type="string", format="date", nullable=true),
     *       @OA\Property(property="budget",       type="number", nullable=true),
     *       @OA\Property(property="priority",     type="string", enum={"low","medium","high","critical"}, default="medium"),
     *       @OA\Property(property="color",        type="string", nullable=true),
     *       @OA\Property(property="tags",         type="array",  @OA\Items(type="string")),
     *       @OA\Property(property="is_billable",  type="boolean", default=true),
     *       @OA\Property(property="member_ids",   type="array",  @OA\Items(type="string", format="uuid"), description="User IDs anggota tim")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Proyek berhasil dibuat",
     *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/ProjectResource"))
     *   )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'client_id'    => ['nullable', 'uuid', 'exists:clients,id'],
            'quotation_id' => ['nullable', 'uuid', 'exists:quotations,id'],
            'manager_id'   => ['required', 'uuid', 'exists:users,id'],
            'start_date'   => ['nullable', 'date'],
            'end_date'     => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget'       => ['nullable', 'numeric', 'min:0'],
            'priority'     => ['sometimes', 'in:low,medium,high,critical'],
            'color'        => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tags'         => ['nullable', 'array'],
            'tags.*'       => ['string', 'max:50'],
            'is_billable'  => ['sometimes', 'boolean'],
            'member_ids'   => ['nullable', 'array'],
            'member_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        $project = Project::create(array_merge($validated, [
            'tenant_id'  => $request->user()->tenant_id,
            'status'     => Project::STATUS_NOT_STARTED,
            'created_by' => $request->user()->id,
        ]));

        // Tambah manager sebagai member
        $project->members()->create([
            'user_id'   => $validated['manager_id'],
            'role'      => 'manager',
            'joined_at' => now(),
        ]);

        // Tambah member lain
        if (! empty($validated['member_ids'])) {
            foreach (array_unique($validated['member_ids']) as $uid) {
                if ($uid !== $validated['manager_id']) {
                    $project->members()->firstOrCreate(
                        ['user_id' => $uid],
                        ['role' => 'member', 'joined_at' => now()]
                    );
                }
            }
        }

        // Buat default Kanban stages
        $project->createDefaultStages();

        return $this->created(
            new ProjectResource($project->load(['client', 'manager', 'members', 'stages'])),
            'Proyek berhasil dibuat dengan 5 stage Kanban default.'
        );
    }

    /**
     * @OA\Get(
     *   path="/projects/{id}",
     *   tags={"Project"},
     *   summary="Detail proyek",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/ProjectResource"))
     *   )
     * )
     */
    public function show(string $id): JsonResponse
    {
        $project = Project::with([
            'client', 'manager', 'members.user',
            'stages', 'quotation', 'createdBy',
        ])
        ->withCount(['tasks', 'tasks as done_tasks_count' => fn($q) => $q->where('is_done', true)])
        ->find($id);

        if (! $project) return $this->notFound('Proyek');
        return $this->ok(new ProjectResource($project));
    }

    /**
     * @OA\Patch(
     *   path="/projects/{id}",
     *   tags={"Project"},
     *   summary="Update proyek",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $project = Project::find($id);
        if (! $project) return $this->notFound('Proyek');

        $validated = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'description'   => ['sometimes', 'nullable', 'string'],
            'status'        => ['sometimes', 'in:not_started,in_progress,on_hold,completed,cancelled'],
            'priority'      => ['sometimes', 'in:low,medium,high,critical'],
            'start_date'    => ['sometimes', 'nullable', 'date'],
            'end_date'      => ['sometimes', 'nullable', 'date'],
            'actual_end_date'=>['sometimes', 'nullable', 'date'],
            'budget'        => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'actual_cost'   => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'color'         => ['sometimes', 'nullable', 'string'],
            'tags'          => ['sometimes', 'nullable', 'array'],
            'is_billable'   => ['sometimes', 'boolean'],
            'manager_id'    => ['sometimes', 'uuid', 'exists:users,id'],
        ]);

        // Set actual dates saat status berubah
        if (isset($validated['status'])) {
            if ($validated['status'] === Project::STATUS_IN_PROGRESS && ! $project->actual_start_date) {
                $validated['actual_start_date'] = now()->toDateString();
            }
            if ($validated['status'] === Project::STATUS_COMPLETED && ! $project->actual_end_date) {
                $validated['actual_end_date'] = now()->toDateString();
            }
        }

        $project->update($validated);
        return $this->ok(new ProjectResource($project->load(['client', 'manager'])), 'Proyek berhasil diperbarui.');
    }

    /**
     * @OA\Delete(
     *   path="/projects/{id}",
     *   tags={"Project"},
     *   summary="Hapus proyek",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Proyek dihapus")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $project = Project::find($id);
        if (! $project) return $this->notFound('Proyek');

        if ($project->isActive()) {
            return $this->error('Proyek yang sedang berjalan tidak dapat dihapus. Ubah status ke Cancelled terlebih dahulu.', 422, 'PROJECT_ACTIVE');
        }

        $project->delete();
        return $this->ok(null, 'Proyek berhasil dihapus.');
    }

    /**
     * @OA\Get(
     *   path="/projects/{id}/kanban",
     *   tags={"Project"},
     *   summary="Data Kanban Board proyek",
     *   description="Mengembalikan semua stage beserta tasks di dalamnya untuk tampilan Kanban.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="assignee_id", in="query", description="Filter task by assignee", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="priority",    in="query", @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="project_id",   type="string"),
     *         @OA\Property(property="project_name", type="string"),
     *         @OA\Property(property="stages", type="array",
     *           @OA\Items(
     *             @OA\Property(property="id",       type="string"),
     *             @OA\Property(property="name",     type="string"),
     *             @OA\Property(property="color",    type="string"),
     *             @OA\Property(property="sequence", type="integer"),
     *             @OA\Property(property="wip_limit",type="integer", nullable=true),
     *             @OA\Property(property="task_count",type="integer"),
     *             @OA\Property(property="tasks",    type="array", @OA\Items(ref="#/components/schemas/TaskResource"))
     *           )
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function kanban(Request $request, string $id): JsonResponse
    {
        $project = Project::find($id);
        if (! $project) return $this->notFound('Proyek');

        $stages = TaskStage::where('project_id', $id)
            ->orderBy('sequence')
            ->get();

        $kanbanData = $stages->map(function (TaskStage $stage) use ($request) {
            $taskQuery = Task::with(['assigneeUsers', 'checklists'])
                ->where('stage_id', $stage->id)
                ->whereNull('parent_task_id')
                ->when($request->assignee_id, fn($q) =>
                    $q->whereHas('assignees', fn($q) => $q->where('user_id', $request->assignee_id))
                )
                ->when($request->priority, fn($q) => $q->where('priority', $request->priority))
                ->orderBy('sequence');

            $tasks = $taskQuery->get();

            return [
                'id'           => $stage->id,
                'name'         => $stage->name,
                'color'        => $stage->color,
                'sequence'     => $stage->sequence,
                'is_done_stage'=> $stage->is_done_stage,
                'wip_limit'    => $stage->wip_limit,
                'task_count'   => $tasks->count(),
                'tasks'        => TaskResource::collection($tasks),
            ];
        });

        return $this->ok([
            'project_id'   => $project->id,
            'project_name' => $project->name,
            'project_status'=> $project->status,
            'stages'       => $kanbanData,
        ]);
    }

    /**
     * @OA\Get(
     *   path="/projects/{id}/timeline",
     *   tags={"Project"},
     *   summary="Data Timeline / Gantt Chart proyek",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="project",    type="object"),
     *         @OA\Property(property="tasks", type="array",
     *           @OA\Items(
     *             @OA\Property(property="id",           type="string"),
     *             @OA\Property(property="title",        type="string"),
     *             @OA\Property(property="start_date",   type="string", format="date"),
     *             @OA\Property(property="due_date",     type="string", format="date"),
     *             @OA\Property(property="progress",     type="integer"),
     *             @OA\Property(property="is_milestone", type="boolean"),
     *             @OA\Property(property="is_done",      type="boolean"),
     *             @OA\Property(property="dependencies", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="assignees",    type="array", @OA\Items(type="string"))
     *           )
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function timeline(string $id): JsonResponse
    {
        $project = Project::find($id);
        if (! $project) return $this->notFound('Proyek');

        $tasks = Task::with(['assigneeUsers', 'dependsOn'])
            ->where('project_id', $id)
            ->whereNull('parent_task_id')
            ->orderBy('start_date')
            ->orderBy('sequence')
            ->get();

        $taskData = $tasks->map(fn($task) => [
            'id'            => $task->id,
            'title'         => $task->title,
            'start_date'    => $task->start_date?->toDateString(),
            'due_date'      => $task->due_date?->toDateString(),
            'progress'      => $task->progress_percent,
            'is_milestone'  => $task->is_milestone,
            'is_done'       => $task->is_done,
            'priority'      => $task->priority,
            'stage_id'      => $task->stage_id,
            'estimated_hours'=> $task->estimated_hours,
            'actual_hours'  => $task->actual_hours,
            'dependencies'  => $task->dependsOn->map(fn($d) => [
                'task_id' => $d->id,
                'type'    => $d->pivot->dependency_type,
            ]),
            'assignees'     => $task->assigneeUsers->map(fn($u) => [
                'id'        => $u->id,
                'full_name' => $u->full_name,
                'avatar_url'=> $u->avatar_url,
            ]),
        ]);

        return $this->ok([
            'project' => [
                'id'         => $project->id,
                'name'       => $project->name,
                'start_date' => $project->start_date?->toDateString(),
                'end_date'   => $project->end_date?->toDateString(),
                'status'     => $project->status,
                'progress'   => $project->progress_percent,
            ],
            'tasks' => $taskData,
        ]);
    }

    /**
     * @OA\Get(
     *   path="/projects/{id}/stats",
     *   tags={"Project"},
     *   summary="Statistik proyek",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function stats(string $id): JsonResponse
    {
        $project = Project::find($id);
        if (! $project) return $this->notFound('Proyek');

        $tasks     = Task::where('project_id', $id)->whereNull('parent_task_id')->get();
        $timeLogs  = \App\Models\TaskTimeLog::whereHas('task', fn($q) => $q->where('project_id', $id))->get();

        $byPriority = $tasks->groupBy('priority')->map->count();
        $byStage    = $tasks->groupBy('stage_id');

        return $this->ok([
            'overview' => [
                'total_tasks'        => $tasks->count(),
                'done_tasks'         => $tasks->where('is_done', true)->count(),
                'overdue_tasks'      => $tasks->filter(fn($t) => $t->isOverdue())->count(),
                'progress_percent'   => $project->progress_percent,
                'budget'             => $project->budget,
                'actual_cost'        => $project->actual_cost,
                'budget_used_pct'    => $project->budget ? round($project->actual_cost / $project->budget * 100, 1) : null,
                'is_budget_warning'  => $project->isBudgetWarning(),
            ],
            'by_priority' => [
                'critical' => $byPriority['critical'] ?? 0,
                'high'     => $byPriority['high']     ?? 0,
                'medium'   => $byPriority['medium']   ?? 0,
                'low'      => $byPriority['low']      ?? 0,
            ],
            'time_tracking' => [
                'total_hours_logged'    => (float) $timeLogs->sum('hours'),
                'total_hours_estimated' => (float) $tasks->sum('estimated_hours'),
                'billable_hours'        => (float) $timeLogs->where('is_billable', true)->sum('hours'),
            ],
            'team' => $project->members()->with('user')->get()->map(fn($m) => [
                'user_id'   => $m->user_id,
                'full_name' => $m->user->full_name,
                'role'      => $m->role,
                'hours'     => (float) $timeLogs->where('user_id', $m->user_id)->sum('hours'),
                'tasks'     => Task::where('project_id', $id)
                    ->whereHas('assignees', fn($q) => $q->where('user_id', $m->user_id))
                    ->count(),
            ]),
        ]);
    }
}
