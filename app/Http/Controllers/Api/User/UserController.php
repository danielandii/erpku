<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Api\BaseController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles')
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($request->search, fn($q, $s) =>
                $q->where(fn($q) => $q->where('full_name','ilike',"%$s%")->orWhere('email','ilike',"%$s%"))
            )
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('full_name')
            ->paginate($request->per_page ?? 15);

        return $this->paginated($users);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::with('roles')->find($id);
        if (!$user) return $this->notFound('User');
        return $this->ok($user);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'full_name'             => ['required','string','max:255'],
            'email'                 => ['required','email','unique:users,email'],
            'password'              => ['required','min:8','confirmed'],
            'phone'                 => ['nullable','string','max:30'],
            'role_ids'              => ['sometimes','array'],
            'role_ids.*'            => ['uuid','exists:roles,id'],
        ]);

        $user = User::create([
            'id'                 => (string) Str::uuid(),
            'tenant_id'          => $request->user()->tenant_id,
            'full_name'          => $v['full_name'],
            'email'              => $v['email'],
            'password'           => Hash::make($v['password']),
            'phone'              => $v['phone'] ?? null,
            'is_active'          => true,
            'is_super_admin'     => false,
            'two_factor_enabled' => false,
            'failed_login_count' => 0,
        ]);

        if (!empty($v['role_ids'])) {
            $rows = array_map(fn($roleId) => [
                'id'          => (string) Str::uuid(),
                'user_id'     => $user->id,
                'role_id'     => $roleId,
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
            ], $v['role_ids']);
            \DB::table('user_roles')->insert($rows);
        }

        return $this->created($user->load('roles'), 'User berhasil dibuat.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) return $this->notFound('User');

        $v = $request->validate([
            'full_name' => ['sometimes','string','max:255'],
            'phone'     => ['sometimes','nullable','string'],
            'is_active' => ['sometimes','boolean'],
        ]);

        $user->update($v);
        return $this->ok($user->load('roles'), 'User berhasil diperbarui.');
    }

    public function destroy(string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) return $this->notFound('User');
        if ($user->is_super_admin) return $this->forbidden('Super Admin tidak dapat dihapus.');
        $user->update(['is_active' => false]);
        return $this->ok(null, 'User dinonaktifkan.');
    }

    public function assignRole(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) return $this->notFound('User');

        $v = $request->validate(['role_ids' => ['required','array'], 'role_ids.*' => ['uuid']]);

        \DB::table('user_roles')->where('user_id', $id)->delete();

        $rows = array_map(fn($roleId) => [
            'id'          => (string) Str::uuid(),
            'user_id'     => $id,
            'role_id'     => $roleId,
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
        ], $v['role_ids']);

        \DB::table('user_roles')->insert($rows);
        return $this->ok($user->load('roles'), 'Role berhasil diperbarui.');
    }
}
