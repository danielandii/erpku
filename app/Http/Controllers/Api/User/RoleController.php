<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\User\RoleResource;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// ══════════════════════════════════════════════════════════
// RoleController
// ══════════════════════════════════════════════════════════
class RoleController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/roles",
     *   tags={"Roles"},
     *   summary="Daftar semua role (system + custom tenant)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/RoleResource")))
     *   )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $roles = Role::with('permissions')
            ->where(fn($q) =>
                $q->whereNull('tenant_id')          // system roles
                  ->orWhere('tenant_id', $tenantId) // custom roles milik tenant ini
            )
            ->orderByRaw("CASE WHEN tenant_id IS NULL THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return $this->ok(RoleResource::collection($roles));
    }

    /**
     * @OA\Post(
     *   path="/roles",
     *   tags={"Roles"},
     *   summary="Buat role kustom baru",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"name","slug"},
     *       @OA\Property(property="name",         type="string", example="Finance Viewer"),
     *       @OA\Property(property="slug",         type="string", example="finance_viewer"),
     *       @OA\Property(property="description",  type="string", nullable=true),
     *       @OA\Property(property="permission_ids",type="array", @OA\Items(type="string", format="uuid"))
     *     )
     *   ),
     *   @OA\Response(response=201, description="Role berhasil dibuat",
     *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/RoleResource"))
     *   )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'slug'           => ['required', 'string', 'max:100', 'alpha_dash'],
            'description'    => ['nullable', 'string', 'max:500'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*'=> ['uuid', 'exists:permissions,id'],
        ]);

        // Cek duplikasi slug per tenant
        $slugExists = Role::where('tenant_id', $tenantId)
            ->where('slug', $validated['slug'])
            ->exists();

        if ($slugExists) {
            return $this->error("Role dengan slug '{$validated['slug']}' sudah ada.", 409, 'SLUG_EXISTS');
        }

        $role = Role::create([
            'tenant_id'   => $tenantId,
            'name'        => $validated['name'],
            'slug'        => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'is_system'   => false,
        ]);

        // Assign permissions
        if (! empty($validated['permission_ids'])) {
            $syncData = [];
            foreach ($validated['permission_ids'] as $permId) {
                $syncData[$permId] = ['granted_by' => $request->user()->id, 'granted_at' => now()];
            }
            $role->permissions()->sync($syncData);
        }

        return $this->created(
            new RoleResource($role->load('permissions')),
            'Role berhasil dibuat.'
        );
    }

    /**
     * @OA\Get(
     *   path="/roles/{id}",
     *   tags={"Roles"},
     *   summary="Detail role beserta semua permission",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $role = Role::with('permissions')->find($id);
        if (! $role) return $this->notFound('Role');

        // Sertakan juga daftar semua permission yang tersedia
        $allPermissions = Permission::orderBy('module')->orderBy('resource')->orderBy('action')->get()
            ->groupBy('module')
            ->map(fn($perms, $module) => [
                'module'      => $module,
                'permissions' => $perms->map(fn($p) => [
                    'id'          => $p->id,
                    'name'        => $p->name,
                    'resource'    => $p->resource,
                    'action'      => $p->action,
                    'description' => $p->description,
                    'is_granted'  => $role->permissions->contains('id', $p->id),
                ]),
            ])->values();

        return $this->ok([
            'role'            => new RoleResource($role),
            'permission_matrix' => $allPermissions,
        ]);
    }

    /**
     * @OA\Patch(
     *   path="/roles/{id}",
     *   tags={"Roles"},
     *   summary="Update role kustom",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       @OA\Property(property="name",        type="string"),
     *       @OA\Property(property="description", type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Role diperbarui")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);
        if (! $role) return $this->notFound('Role');

        if ($role->is_system) {
            return $this->error('System role tidak dapat diedit.', 422, 'SYSTEM_ROLE');
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $role->update($validated);

        // Flush permission cache semua user yang pakai role ini
        $role->users->each(fn($u) => cache()->forget("user_permissions_{$u->id}"));

        return $this->ok(new RoleResource($role->load('permissions')), 'Role berhasil diperbarui.');
    }

    /**
     * @OA\Delete(
     *   path="/roles/{id}",
     *   tags={"Roles"},
     *   summary="Hapus role kustom",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Role dihapus")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $role = Role::find($id);
        if (! $role) return $this->notFound('Role');

        if ($role->is_system) {
            return $this->error('System role tidak dapat dihapus.', 422, 'SYSTEM_ROLE');
        }

        if ($role->users()->exists()) {
            return $this->error(
                'Role masih digunakan oleh ' . $role->users()->count() . ' user. Unassign terlebih dahulu.',
                422, 'ROLE_IN_USE'
            );
        }

        $role->delete();
        return $this->ok(null, 'Role berhasil dihapus.');
    }

    /**
     * @OA\Put(
     *   path="/roles/{id}/permissions",
     *   tags={"Roles"},
     *   summary="Sync permission ke role (replace all)",
     *   description="Mengganti semua permission role dengan yang baru. Kirim array kosong untuk hapus semua permission.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"permission_ids"},
     *       @OA\Property(property="permission_ids", type="array",
     *         description="Array UUID permission yang aktif",
     *         @OA\Items(type="string", format="uuid")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=200, description="Permission berhasil diperbarui",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="role_id",          type="string"),
     *         @OA\Property(property="permission_count", type="integer")
     *       )
     *     )
     *   )
     * )
     */
    public function syncPermissions(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);
        if (! $role) return $this->notFound('Role');

        if ($role->is_system && ! $request->user()->is_super_admin) {
            return $this->error('Hanya Super Admin yang dapat mengubah permission system role.', 403, 'FORBIDDEN');
        }

        $request->validate([
            'permission_ids'   => ['required', 'array'],
            'permission_ids.*' => ['uuid', 'exists:permissions,id'],
        ]);

        $syncData = [];
        foreach ($request->permission_ids as $permId) {
            $syncData[$permId] = [
                'granted_by' => $request->user()->id,
                'granted_at' => now(),
            ];
        }

        $role->permissions()->sync($syncData);

        // Flush permission cache semua user yang pakai role ini
        $role->load('users');
        $role->users->each(fn($u) => cache()->forget("user_permissions_{$u->id}"));

        return $this->ok([
            'role_id'          => $role->id,
            'permission_count' => count($request->permission_ids),
        ], 'Permission role berhasil diperbarui.');
    }
}

// ══════════════════════════════════════════════════════════
// PermissionController
// ══════════════════════════════════════════════════════════
// class PermissionController extends BaseController
// {
//     /**
//      * @OA\Get(
//      *   path="/permissions",
//      *   tags={"Roles"},
//      *   summary="Semua permission yang tersedia di sistem",
//      *   description="Mengembalikan daftar permission dikelompokkan per modul untuk keperluan permission matrix UI.",
//      *   security={{"bearerAuth":{}}},
//      *   @OA\Parameter(name="module", in="query", description="Filter per modul", @OA\Schema(type="string")),
//      *   @OA\Response(response=200, description="OK",
//      *     @OA\JsonContent(
//      *       @OA\Property(property="data", type="array",
//      *         @OA\Items(
//      *           @OA\Property(property="module",      type="string"),
//      *           @OA\Property(property="permissions", type="array",
//      *             @OA\Items(
//      *               @OA\Property(property="id",          type="string", format="uuid"),
//      *               @OA\Property(property="name",        type="string"),
//      *               @OA\Property(property="resource",    type="string"),
//      *               @OA\Property(property="action",      type="string"),
//      *               @OA\Property(property="description", type="string")
//      *             )
//      *           )
//      *         )
//      *       )
//      *     )
//      *   )
//      * )
//      */
//     public function index(Request $request): JsonResponse
//     {
//         $perms = Permission::when($request->module, fn($q) => $q->where('module', $request->module))
//             ->orderBy('module')
//             ->orderBy('resource')
//             ->orderBy('action')
//             ->get();

//         $grouped = $perms->groupBy('module')->map(fn($items, $module) => [
//             'module'      => $module,
//             'label'       => $this->moduleLabel($module),
//             'permissions' => $items->map(fn($p) => [
//                 'id'          => $p->id,
//                 'name'        => $p->name,
//                 'resource'    => $p->resource,
//                 'action'      => $p->action,
//                 'description' => $p->description,
//             ])->values(),
//         ])->values();

//         return $this->ok($grouped);
//     }

//     private function moduleLabel(string $module): string
//     {
//         return match ($module) {
//             'user'      => 'User & Akses',
//             'hrd'       => 'HRD',
//             'marketing' => 'Marketing & CRM',
//             'finance'   => 'Finance',
//             'project'   => 'Project',
//             default     => ucfirst($module),
//         };
//     }
// }

// // ══════════════════════════════════════════════════════════
// // AuditLogController
// // ══════════════════════════════════════════════════════════
// class AuditLogController extends BaseController
// {
//     /**
//      * @OA\Get(
//      *   path="/audit-logs",
//      *   tags={"Users"},
//      *   summary="Audit trail sistem",
//      *   description="Riwayat semua perubahan data yang tercatat secara otomatis.",
//      *   security={{"bearerAuth":{}}},
//      *   @OA\Parameter(name="user_id",       in="query", @OA\Schema(type="string", format="uuid")),
//      *   @OA\Parameter(name="module",        in="query", @OA\Schema(type="string")),
//      *   @OA\Parameter(name="action",        in="query", @OA\Schema(type="string")),
//      *   @OA\Parameter(name="resource_type", in="query", @OA\Schema(type="string")),
//      *   @OA\Parameter(name="date_from",     in="query", @OA\Schema(type="string", format="date")),
//      *   @OA\Parameter(name="date_to",       in="query", @OA\Schema(type="string", format="date")),
//      *   @OA\Parameter(name="per_page",      in="query", @OA\Schema(type="integer", default=30)),
//      *   @OA\Response(response=200, description="OK",
//      *     @OA\JsonContent(
//      *       @OA\Property(property="data", type="array",
//      *         @OA\Items(
//      *           @OA\Property(property="id",            type="string", format="uuid"),
//      *           @OA\Property(property="actor",         type="string"),
//      *           @OA\Property(property="module",        type="string"),
//      *           @OA\Property(property="action",        type="string"),
//      *           @OA\Property(property="resource_type", type="string"),
//      *           @OA\Property(property="resource_id",   type="string", nullable=true),
//      *           @OA\Property(property="old_values",    type="object", nullable=true),
//      *           @OA\Property(property="new_values",    type="object", nullable=true),
//      *           @OA\Property(property="ip_address",    type="string", nullable=true),
//      *           @OA\Property(property="created_at",    type="string", format="date-time")
//      *         )
//      *       )
//      *     )
//      *   )
//      * )
//      */
//     public function index(Request $request): JsonResponse
//     {
//         $tenantId = $request->user()->tenant_id;

//         $logs = AuditLog::with('user')
//             ->where('tenant_id', $tenantId)
//             ->when($request->user_id,       fn($q) => $q->where('user_id', $request->user_id))
//             ->when($request->module,        fn($q) => $q->where('module', $request->module))
//             ->when($request->action,        fn($q) => $q->where('action', $request->action))
//             ->when($request->resource_type, fn($q) => $q->where('resource_type', $request->resource_type))
//             ->when($request->date_from,     fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
//             ->when($request->date_to,       fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
//             ->orderByDesc('created_at')
//             ->paginate($request->per_page ?? 30);

//         return response()->json([
//             'success' => true,
//             'message' => 'OK',
//             'data'    => $logs->map(fn($log) => [
//                 'id'            => $log->id,
//                 'actor'         => $log->user?->full_name ?? 'System',
//                 'actor_email'   => $log->user?->email,
//                 'module'        => $log->module,
//                 'action'        => $log->action,
//                 'resource_type' => $log->resource_type,
//                 'resource_id'   => $log->resource_id,
//                 'old_values'    => $log->old_values,
//                 'new_values'    => $log->new_values,
//                 'ip_address'    => $log->ip_address,
//                 'notes'         => $log->notes,
//                 'created_at'    => $log->created_at?->toIso8601String(),
//             ]),
//             'meta' => [
//                 'current_page' => $logs->currentPage(),
//                 'last_page'    => $logs->lastPage(),
//                 'per_page'     => $logs->perPage(),
//                 'total'        => $logs->total(),
//             ],
//         ]);
//     }
// }
