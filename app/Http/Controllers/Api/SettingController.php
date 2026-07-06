<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\Setting;
use App\Models\TenantModuleConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/settings",
     *   tags={"Settings"},
     *   summary="Ambil semua pengaturan tenant",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="settings", type="array",
     *           @OA\Items(
     *             @OA\Property(property="key",   type="string"),
     *             @OA\Property(property="value", type="string"),
     *             @OA\Property(property="type",  type="string")
     *           )
     *         ),
     *         @OA\Property(property="modules", type="array",
     *           @OA\Items(
     *             @OA\Property(property="module_name", type="string"),
     *             @OA\Property(property="is_enabled",  type="boolean")
     *           )
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $settings = Setting::where('tenant_id', $tenantId)
            ->orderBy('key')
            ->get()
            ->map(fn($s) => [
                'key'         => $s->key,
                'value'       => $s->value,
                'type'        => $s->type,
                'description' => $s->description,
            ]);

        $modules = TenantModuleConfig::where('tenant_id', $tenantId)
            ->get()
            ->map(fn($m) => [
                'module_name' => $m->module_name,
                'is_enabled'  => $m->is_enabled,
                'enabled_at'  => $m->enabled_at?->toIso8601String(),
            ]);

        return $this->ok([
            'settings' => $settings,
            'modules'  => $modules,
        ]);
    }

    /**
     * @OA\Patch(
     *   path="/settings",
     *   tags={"Settings"},
     *   summary="Update satu atau beberapa pengaturan",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="settings", type="object",
     *         description="Key-value pairs pengaturan yang ingin diubah",
     *         example={"hrd.late_deduction_per_minute": "5000", "finance.ppn_rate": "11"}
     *       )
     *     )
     *   ),
     *   @OA\Response(response=200, description="Pengaturan berhasil disimpan")
     * )
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'settings'   => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $tenantId = $request->user()->tenant_id;

        foreach ($request->settings as $key => $value) {
            Setting::setValue($tenantId, $key, $value ?? '');
        }

        return $this->ok(null, count($request->settings) . ' pengaturan berhasil disimpan.');
    }

    /**
     * @OA\Get(
     *   path="/settings/modules",
     *   tags={"Settings"},
     *   summary="Status modul yang tersedia",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function modules(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $allModules = ['user', 'hrd', 'marketing', 'finance', 'project'];

        $configs = TenantModuleConfig::where('tenant_id', $tenantId)->get()->keyBy('module_name');

        $result = collect($allModules)->map(fn($module) => [
            'module_name'  => $module,
            'label'        => $this->moduleLabel($module),
            'description'  => $this->moduleDescription($module),
            'is_required'  => $module === 'user',
            'is_enabled'   => $configs[$module]?->is_enabled ?? false,
            'enabled_at'   => $configs[$module]?->enabled_at?->toIso8601String(),
        ]);

        return $this->ok($result);
    }

    /**
     * @OA\Patch(
     *   path="/settings/modules/{module}",
     *   tags={"Settings"},
     *   summary="Aktifkan / nonaktifkan modul untuk tenant",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="module", in="path", required=true,
     *     @OA\Schema(type="string", enum={"hrd","marketing","finance","project"})
     *   ),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"is_enabled"},
     *       @OA\Property(property="is_enabled", type="boolean")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Status modul berhasil diubah")
     * )
     */
    public function toggleModule(Request $request, string $module): JsonResponse
    {
        $request->validate(['is_enabled' => ['required', 'boolean']]);

        $allowed = ['hrd', 'marketing', 'finance', 'project'];
        if (! in_array($module, $allowed)) {
            return $this->error("Modul '{$module}' tidak valid atau tidak dapat diubah.", 422, 'INVALID_MODULE');
        }

        $tenantId = $request->user()->tenant_id;
        $isEnabled = $request->boolean('is_enabled');

        TenantModuleConfig::updateOrCreate(
            ['tenant_id' => $tenantId, 'module_name' => $module],
            [
                'is_enabled' => $isEnabled,
                'enabled_at' => $isEnabled ? now() : null,
                'enabled_by' => $request->user()->id,
            ]
        );

        // Flush modul cache
        Cache::forget("module_enabled_{$tenantId}_{$module}");

        $action = $isEnabled ? 'diaktifkan' : 'dinonaktifkan';
        return $this->ok(['module' => $module, 'is_enabled' => $isEnabled], "Modul '{$module}' berhasil {$action}.");
    }

    private function moduleLabel(string $module): string
    {
        return match ($module) {
            'user'      => 'User & Akses',
            'hrd'       => 'Human Resource (HRD)',
            'marketing' => 'Marketing & CRM',
            'finance'   => 'Finance & Akuntansi',
            'project'   => 'Project Management',
            default     => ucfirst($module),
        };
    }

    private function moduleDescription(string $module): string
    {
        return match ($module) {
            'user'      => 'Manajemen user, role, dan permission. Wajib aktif.',
            'hrd'       => 'Karyawan, presensi, cuti, dan penggajian.',
            'marketing' => 'Leads pipeline, quotation, dan manajemen klien.',
            'finance'   => 'Invoice, pembayaran, jurnal, dan laporan keuangan.',
            'project'   => 'Proyek, Kanban board, timeline, dan time tracking.',
            default     => '',
        };
    }
}
