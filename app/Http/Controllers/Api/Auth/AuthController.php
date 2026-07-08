<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\BaseController;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Annotations as OA;   // <-- this is critical

class AuthController extends BaseController
{
    /**
     * @OA\Post(path="/auth/login", tags={"Auth"}, summary="Login",
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(required={"email","password"},
     *       @OA\Property(property="email",    type="string", format="email"),
     *       @OA\Property(property="password", type="string", format="password")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Login berhasil"),
     *   @OA\Response(response=401, description="Kredensial tidak valid"),
     *   @OA\Response(response=429, description="Terlalu banyak percobaan login")
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'login:' . Str::lower($request->email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return $this->error(
                "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
                429, 'TOO_MANY_ATTEMPTS'
            );
        }

        $user = User::with(['roles.permissions', 'tenant'])
            ->where('email', $request->email)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 300);
            return $this->error('Email atau password tidak valid.', 401, 'INVALID_CREDENTIALS');
        }

        if (! $user->is_active) {
            return $this->error('Akun Anda dinonaktifkan. Hubungi administrator.', 403, 'ACCOUNT_INACTIVE');
        }

        RateLimiter::clear($throttleKey);

        // Buat access token (Sanctum)
        $expiresIn    = (int) config('sanctum.token_expiration', 15) * 60; // detik
        $accessToken  = $user->createToken('api', ['*'], now()->addSeconds($expiresIn));

        // Buat refresh token
        $refreshToken = Str::random(80);
        RefreshToken::create([
            'user_id'     => $user->id,
            'token'       => hash('sha256', $refreshToken),
            'device_name' => $request->device_name ?? ($request->userAgent() ?? 'unknown'),
            'expires_at'  => now()->addDays(7),
        ]);

        // Update last login
        $user->update([
            'last_login_at'     => now(),
            'last_login_ip'     => $request->ip(),
            'failed_login_count'=> 0,
        ]);

        // Log login history
        try {
            \DB::table('login_histories')->insert([
                'id'         => (string) Str::uuid(),
                'user_id'    => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status'     => 'success',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception) { /* best-effort */ }

        return $this->ok([
            'access_token'  => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => $expiresIn,
            'user'          => $this->formatUser($user),
        ], 'Login berhasil!');
    }

    /**
     * @OA\Post(path="/auth/refresh", tags={"Auth"}, summary="Refresh access token",
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(required={"refresh_token"},
     *       @OA\Property(property="refresh_token", type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Token diperbarui"),
     *   @OA\Response(response=401, description="Refresh token tidak valid")
     * )
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => ['required', 'string']]);

        $hashed = hash('sha256', $request->refresh_token);
        $token  = RefreshToken::where('token', $hashed)
            ->where('is_revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if (! $token) {
            return $this->error('Refresh token tidak valid atau kadaluarsa.', 401, 'INVALID_REFRESH_TOKEN');
        }

        $user = User::with(['roles.permissions', 'tenant'])->find($token->user_id);
        if (! $user || ! $user->is_active) {
            return $this->error('Akun tidak aktif.', 403, 'ACCOUNT_INACTIVE');
        }

        // Revoke token lama
        $token->update(['is_revoked' => true]);

        // Revoke semua Sanctum token lama
        $user->tokens()->delete();

        // Buat token baru
        $expiresIn    = (int) config('sanctum.token_expiration', 15) * 60;
        $accessToken  = $user->createToken('api', ['*'], now()->addSeconds($expiresIn));
        $refreshToken = Str::random(80);

        RefreshToken::create([
            'user_id'     => $user->id,
            'token'       => hash('sha256', $refreshToken),
            'device_name' => $token->device_name,
            'expires_at'  => now()->addDays(7),
        ]);

        $token->update(['last_used_at' => now()]);

        return $this->ok([
            'access_token'  => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => $expiresIn,
            'user'          => $this->formatUser($user),
        ], 'Token diperbarui.');
    }

    /**
     * @OA\Post(path="/auth/logout", tags={"Auth"}, summary="Logout",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Logout berhasil")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Revoke semua refresh token user ini
        RefreshToken::where('user_id', $request->user()->id)->update(['is_revoked' => true]);

        return $this->ok(null, 'Logout berhasil.');
    }

    /**
     * @OA\Get(path="/auth/me", tags={"Auth"}, summary="Data user yang sedang login",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function me(Request $request): JsonResponse
    {
        $user = User::with(['roles.permissions', 'tenant', 'employee.department', 'employee.position'])
            ->find($request->user()->id);

        return $this->ok($this->formatUser($user));
    }

    /**
     * @OA\Post(path="/auth/change-password", tags={"Auth"}, summary="Ganti password",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(required={"current_password","password","password_confirmation"},
     *       @OA\Property(property="current_password", type="string"),
     *       @OA\Property(property="password",         type="string", minLength=8),
     *       @OA\Property(property="password_confirmation", type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Password berhasil diubah")
     * )
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required'],
            'password'         => ['required', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        if (! Hash::check($request->current_password, $user->password)) {
            return $this->error('Password saat ini tidak sesuai.', 422, 'WRONG_CURRENT_PASSWORD');
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Revoke semua token supaya login ulang
        $user->tokens()->delete();
        RefreshToken::where('user_id', $user->id)->update(['is_revoked' => true]);

        return $this->ok(null, 'Password berhasil diubah. Silakan login kembali.');
    }

    /**
     * @OA\Post(path="/auth/forgot-password", tags={"Auth"}, summary="Kirim link reset password",
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(required={"email"},
     *       @OA\Property(property="email", type="string", format="email")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Email terkirim")
     * )
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        // Selalu return OK agar tidak leak info keberadaan email
        return $this->ok(null, 'Jika email terdaftar, link reset password telah dikirim.');
    }

    /**
     * @OA\Post(path="/auth/reset-password", tags={"Auth"}, summary="Reset password dengan token",
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(required={"token","email","password","password_confirmation"},
     *       @OA\Property(property="token",    type="string"),
     *       @OA\Property(property="email",    type="string", format="email"),
     *       @OA\Property(property="password", type="string", minLength=8),
     *       @OA\Property(property="password_confirmation", type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Password berhasil direset")
     * )
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->ok(null, 'Password berhasil direset. Silakan login.');
        }

        return $this->error('Link reset password tidak valid atau sudah kadaluarsa.', 422, 'INVALID_RESET_TOKEN');
    }

    /**
     * @OA\Get(path="/auth/login-history", tags={"Auth"}, summary="Riwayat login user",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=10)),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function loginHistory(Request $request): JsonResponse
    {
        $history = \DB::table('login_histories')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit($request->per_page ?? 10)
            ->get(['id', 'ip_address', 'user_agent', 'device', 'status', 'created_at']);

        return $this->ok($history);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function formatUser(User $user): array
    {
        // Kumpulkan semua permission dari semua role
        $permissions = collect();
        foreach ($user->roles ?? [] as $role) {
            foreach ($role->permissions ?? [] as $perm) {
                $permissions->push($perm->name);
            }
        }

        // Enabled modules dari tenant config
        $modules = \DB::table('tenant_module_configs')
            ->where('tenant_id', $user->tenant_id)
            ->where('is_enabled', true)
            ->pluck('module_name')
            ->toArray();

        return [
            'id'             => $user->id,
            'tenant_id'      => $user->tenant_id,
            'full_name'      => $user->full_name,
            'email'          => $user->email,
            'phone'          => $user->phone ?? null,
            'avatar_url'     => $user->avatar_url ?? null,
            'is_active'      => $user->is_active,
            'is_super_admin' => $user->is_super_admin,
            'last_login_at'  => $user->last_login_at?->toIso8601String(),
            'roles'          => ($user->roles ?? collect())->map(fn($r) => [
                'id'   => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
            ])->values(),
            'permissions'    => $user->is_super_admin
                ? ['*']
                : $permissions->unique()->values()->toArray(),
            'modules'        => $modules,
            'employee'       => $user->employee ? [
                'id'              => $user->employee->id,
                'employee_number' => $user->employee->employee_number,
                'department'      => $user->employee->department?->name,
                'position'        => $user->employee->position?->name,
            ] : null,
        ];
    }
}
