<?php

namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Api\BaseController;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TaskStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// ══════════════════════════════════════════════════════════
// TaskStageController — Kelola kolom Kanban per proyek
// ══════════════════════════════════════════════════════════
class TaskStageController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/projects/{projectId}/stages",
     *   tags={"Project"},
     *   summary="Daftar stage / kolom Kanban proyek",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="projectId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="array",
     *         @OA\Items(
     *           @OA\Property(property="id",           type="string", format="uuid"),
     *           @OA\Property(property="name",         type="string"),
     *           @OA\Property(property="color",        type="string"),
     *           @OA\Property(property="sequence",     type="integer"),
     *           @OA\Property(property="is_done_stage",type="boolean"),
     *           @OA\Property(property="wip_limit",    type="integer", nullable=true),
     *           @OA\Property(property="task_count",   type="integer")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function index(string $projectId): JsonResponse
    {
        $project = Project::find($projectId);
        if (! $project) return $this->notFound('Proyek');

        $stages = TaskStage::where('project_id', $projectId)
            ->withCount('tasks')
            ->orderBy('sequence')
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'name'         => $s->name,
                'color'        => $s->color,
                'sequence'     => $s->sequence,
                'is_done_stage'=> $s->is_done_stage,
                'wip_limit'    => $s->wip_limit,
                'task_count'   => $s->tasks_count,
                'is_at_limit'  => $s->isAtWipLimit(),
            ]);

        return $this->ok($stages);
    }

    /**
     * @OA\Post(
     *   path="/projects/{projectId}/stages",
     *   tags={"Project"},
     *   summary="Tambah kolom Kanban baru",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="projectId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"name"},
     *       @OA\Property(property="name",          type="string", example="In Review"),
     *       @OA\Property(property="color",         type="string", example="#8B5CF6"),
     *       @OA\Property(property="sequence",      type="integer", description="Urutan kolom (auto jika kosong)"),
     *       @OA\Property(property="is_done_stage", type="boolean", default=false),
     *       @OA\Property(property="wip_limit",     type="integer", nullable=true, description="0 = tidak terbatas")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Stage berhasil ditambahkan")
     * )
     */
    public function store(Request $request, string $projectId): JsonResponse
    {
        $project = Project::find($projectId);
        if (! $project) return $this->notFound('Proyek');

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'color'         => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sequence'      => ['nullable', 'integer', 'min:0'],
            'is_done_stage' => ['sometimes', 'boolean'],
            'wip_limit'     => ['nullable', 'integer', 'min:0'],
        ]);

        // Auto-set sequence di akhir jika tidak diisi
        if (! isset($validated['sequence'])) {
            $validated['sequence'] = (TaskStage::where('project_id', $projectId)->max('sequence') ?? 0) + 10;
        }

        $stage = TaskStage::create(array_merge($validated, ['project_id' => $projectId]));

        return $this->created([
            'id'           => $stage->id,
            'name'         => $stage->name,
            'color'        => $stage->color,
            'sequence'     => $stage->sequence,
            'is_done_stage'=> $stage->is_done_stage,
            'wip_limit'    => $stage->wip_limit,
        ], 'Kolom Kanban berhasil ditambahkan.');
    }

    /**
     * @OA\Patch(
     *   path="/projects/{projectId}/stages/{id}",
     *   tags={"Project"},
     *   summary="Update kolom Kanban",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="projectId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="id",        in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       @OA\Property(property="name",          type="string"),
     *       @OA\Property(property="color",         type="string"),
     *       @OA\Property(property="is_done_stage", type="boolean"),
     *       @OA\Property(property="wip_limit",     type="integer", nullable=true)
     *     )
     *   ),
     *   @OA\Response(response=200, description="Stage diperbarui")
     * )
     */
    public function update(Request $request, string $projectId, string $id): JsonResponse
    {
        $stage = TaskStage::where('project_id', $projectId)->find($id);
        if (! $stage) return $this->notFound('Stage');

        $validated = $request->validate([
            'name'          => ['sometimes', 'string', 'max:100'],
            'color'         => ['sometimes', 'nullable', 'string'],
            'is_done_stage' => ['sometimes', 'boolean'],
            'wip_limit'     => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $stage->update($validated);

        return $this->ok([
            'id'           => $stage->id,
            'name'         => $stage->name,
            'color'        => $stage->color,
            'is_done_stage'=> $stage->is_done_stage,
            'wip_limit'    => $stage->wip_limit,
        ], 'Kolom Kanban berhasil diperbarui.');
    }

    /**
     * @OA\Delete(
     *   path="/projects/{projectId}/stages/{id}",
     *   tags={"Project"},
     *   summary="Hapus kolom Kanban",
     *   description="Stage tidak bisa dihapus jika masih ada task di dalamnya.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="projectId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="id",        in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Stage dihapus"),
     *   @OA\Response(response=422, description="Stage masih memiliki task")
     * )
     */
    public function destroy(string $projectId, string $id): JsonResponse
    {
        $stage = TaskStage::where('project_id', $projectId)->find($id);
        if (! $stage) return $this->notFound('Stage');

        if ($stage->tasks()->exists()) {
            return $this->error(
                "Kolom '{$stage->name}' masih memiliki task. Pindahkan semua task terlebih dahulu.",
                422, 'STAGE_HAS_TASKS'
            );
        }

        $stage->delete();
        return $this->ok(null, 'Kolom Kanban berhasil dihapus.');
    }

    /**
     * @OA\Post(
     *   path="/projects/{projectId}/stages/reorder",
     *   tags={"Project"},
     *   summary="Urutkan ulang kolom Kanban (drag & drop)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="projectId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"ordered_ids"},
     *       @OA\Property(property="ordered_ids", type="array",
     *         description="Array ID stage dalam urutan baru",
     *         @OA\Items(type="string", format="uuid")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=200, description="Urutan kolom berhasil disimpan")
     * )
     */
    public function reorder(Request $request, string $projectId): JsonResponse
    {
        $request->validate([
            'ordered_ids'   => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['uuid'],
        ]);

        foreach ($request->ordered_ids as $index => $stageId) {
            TaskStage::where('id', $stageId)
                ->where('project_id', $projectId)
                ->update(['sequence' => ($index + 1) * 10]);
        }

        return $this->ok(null, 'Urutan kolom Kanban berhasil disimpan.');
    }
}

// ══════════════════════════════════════════════════════════
// ProjectMemberController — Kelola anggota tim proyek
// ══════════════════════════════════════════════════════════
class ProjectMemberController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/projects/{projectId}/members",
     *   tags={"Project"},
     *   summary="Daftar anggota tim proyek",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="projectId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="array",
     *         @OA\Items(
     *           @OA\Property(property="id",        type="string", format="uuid"),
     *           @OA\Property(property="user_id",   type="string", format="uuid"),
     *           @OA\Property(property="full_name", type="string"),
     *           @OA\Property(property="avatar_url",type="string", nullable=true),
     *           @OA\Property(property="role",      type="string", enum={"manager","lead","member","observer"}),
     *           @OA\Property(property="joined_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function index(string $projectId): JsonResponse
    {
        $project = Project::find($projectId);
        if (! $project) return $this->notFound('Proyek');

        $members = ProjectMember::where('project_id', $projectId)
            ->with('user')
            ->get()
            ->map(fn($m) => [
                'id'         => $m->id,
                'user_id'    => $m->user_id,
                'full_name'  => $m->user?->full_name,
                'email'      => $m->user?->email,
                'avatar_url' => $m->user?->avatar_url,
                'initials'   => collect(explode(' ', $m->user?->full_name ?? ''))->map(fn($w) => strtoupper($w[0] ?? ''))->take(2)->join(''),
                'role'       => $m->role,
                'joined_at'  => $m->joined_at?->toIso8601String(),
            ]);

        return $this->ok($members);
    }

    /**
     * @OA\Post(
     *   path="/projects/{projectId}/members",
     *   tags={"Project"},
     *   summary="Tambah anggota ke proyek",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="projectId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"user_id"},
     *       @OA\Property(property="user_id", type="string", format="uuid"),
     *       @OA\Property(property="role",    type="string", enum={"manager","lead","member","observer"}, default="member")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Anggota berhasil ditambahkan"),
     *   @OA\Response(response=409, description="User sudah menjadi anggota")
     * )
     */
    public function store(Request $request, string $projectId): JsonResponse
    {
        $project = Project::find($projectId);
        if (! $project) return $this->notFound('Proyek');

        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role'    => ['sometimes', 'in:manager,lead,member,observer'],
        ]);

        $exists = ProjectMember::where('project_id', $projectId)
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return $this->error('User sudah menjadi anggota proyek ini.', 409, 'ALREADY_MEMBER');
        }

        $member = ProjectMember::create([
            'project_id' => $projectId,
            'user_id'    => $validated['user_id'],
            'role'       => $validated['role'] ?? ProjectMember::ROLE_MEMBER,
            'joined_at'  => now(),
        ]);

        return $this->created([
            'id'      => $member->id,
            'user_id' => $member->user_id,
            'role'    => $member->role,
        ], 'Anggota berhasil ditambahkan ke proyek.');
    }

    /**
     * @OA\Delete(
     *   path="/projects/{projectId}/members/{id}",
     *   tags={"Project"},
     *   summary="Hapus anggota dari proyek",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="projectId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="id",        in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Anggota dihapus")
     * )
     */
    public function destroy(Request $request, string $projectId, string $id): JsonResponse
    {
        $member = ProjectMember::where('project_id', $projectId)->find($id);
        if (! $member) return $this->notFound('Member');

        // Tidak boleh hapus project manager
        if ($member->role === ProjectMember::ROLE_MANAGER) {
            $project = Project::find($projectId);
            if ($project && $project->manager_id === $member->user_id) {
                return $this->error(
                    'Tidak dapat menghapus Project Manager dari tim. Ganti manager proyek terlebih dahulu.',
                    422, 'CANNOT_REMOVE_MANAGER'
                );
            }
        }

        $member->delete();
        return $this->ok(null, 'Anggota berhasil dihapus dari proyek.');
    }
}
