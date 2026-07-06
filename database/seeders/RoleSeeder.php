<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $now      = now();

        // ── System Roles ───────────────────────────────────────
        $roles = [
            [
                'id'          => 'role-super-admin-000000000001',
                'tenant_id'   => null,           // Global, tidak terikat tenant
                'name'        => 'Super Admin',
                'slug'        => 'super_admin',
                'description' => 'Akses penuh ke seluruh sistem termasuk pengaturan tenant',
                'is_system'   => true,
            ],
            [
                'id'          => 'role-admin-tenant-000000000002',
                'tenant_id'   => $tenantId,
                'name'        => 'Admin',
                'slug'        => 'admin',
                'description' => 'Admin perusahaan — kelola user, modul, dan konfigurasi',
                'is_system'   => true,
            ],
            [
                'id'          => 'role-hr-manager-00000000000003',
                'tenant_id'   => $tenantId,
                'name'        => 'HR Manager',
                'slug'        => 'hr_manager',
                'description' => 'Kelola seluruh modul HRD: karyawan, presensi, cuti, gaji',
                'is_system'   => true,
            ],
            [
                'id'          => 'role-finance-manager-000000004',
                'tenant_id'   => $tenantId,
                'name'        => 'Finance Manager',
                'slug'        => 'finance_manager',
                'description' => 'Kelola invoice, pembayaran, jurnal, dan laporan keuangan',
                'is_system'   => true,
            ],
            [
                'id'          => 'role-sales-manager-0000000005',
                'tenant_id'   => $tenantId,
                'name'        => 'Sales Manager',
                'slug'        => 'sales_manager',
                'description' => 'Kelola leads, quotation, dan klien',
                'is_system'   => true,
            ],
            [
                'id'          => 'role-project-manager-000000006',
                'tenant_id'   => $tenantId,
                'name'        => 'Project Manager',
                'slug'        => 'project_manager',
                'description' => 'Kelola proyek, task, dan anggota tim',
                'is_system'   => true,
            ],
            [
                'id'          => 'role-staff-general-0000000007',
                'tenant_id'   => $tenantId,
                'name'        => 'Staff',
                'slug'        => 'staff',
                'description' => 'Akses dasar: presensi, cuti, lihat task yang di-assign',
                'is_system'   => true,
            ],
            [
                'id'          => 'role-viewer-readonly-000000008',
                'tenant_id'   => $tenantId,
                'name'        => 'Viewer',
                'slug'        => 'viewer',
                'description' => 'Read-only access ke modul yang diizinkan',
                'is_system'   => true,
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insertOrIgnore(array_merge($role, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // ── Assign Permissions ke Roles ────────────────────────
        // Ambil semua permission untuk di-mapping
        $allPerms = DB::table('permissions')->pluck('id', 'name');

        // Super Admin: semua permission
        $superAdminPermsId = 'role-super-admin-000000000001';
        $this->assignPermissions($superAdminPermsId, $allPerms->values()->toArray());

        // HR Manager: semua permission HRD + read user
        $hrPerms = $allPerms->filter(fn($id, $name) =>
            str_starts_with($name, 'hrd.') ||
            in_array($name, ['user.users.read'])
        )->values()->toArray();
        $this->assignPermissions('role-hr-manager-00000000000003', $hrPerms);

        // Finance Manager: semua permission Finance + read clients + read users
        $financePerms = $allPerms->filter(fn($id, $name) =>
            str_starts_with($name, 'finance.') ||
            in_array($name, ['marketing.clients.read', 'marketing.quotations.read', 'user.users.read'])
        )->values()->toArray();
        $this->assignPermissions('role-finance-manager-000000004', $financePerms);

        // Sales Manager: semua permission Marketing
        $salesPerms = $allPerms->filter(fn($id, $name) =>
            str_starts_with($name, 'marketing.')
        )->values()->toArray();
        $this->assignPermissions('role-sales-manager-0000000005', $salesPerms);

        // Project Manager: semua permission Project
        $projectPerms = $allPerms->filter(fn($id, $name) =>
            str_starts_with($name, 'project.')
        )->values()->toArray();
        $this->assignPermissions('role-project-manager-000000006', $projectPerms);

        // Staff: hanya create attendance, create leave, read task
        $staffPerms = $allPerms->filter(fn($id, $name) =>
            in_array($name, [
                'hrd.attendance.create',
                'hrd.attendance.read',
                'hrd.leave.create',
                'hrd.leave.read',
                'project.tasks.read',
                'project.tasks.update',
                'project.time_logs.create',
                'project.time_logs.read',
            ])
        )->values()->toArray();
        $this->assignPermissions('role-staff-general-0000000007', $staffPerms);

        // Viewer: hanya read permission
        $viewerPerms = $allPerms->filter(fn($id, $name) =>
            str_ends_with($name, '.read')
        )->values()->toArray();
        $this->assignPermissions('role-viewer-readonly-000000008', $viewerPerms);

        $this->command->info('✓ Roles & permissions seeded.');
    }

    private function assignPermissions(string $roleId, array $permissionIds): void
    {
        // Ambil user ID default untuk granted_by (akan diisi setelah UserSeeder)
        $grantedBy = 'user-super-admin-00000000000001';
        $now       = now();

        $rows = array_map(fn($permId) => [
            'id'            => Str::uuid(),
            'role_id'       => $roleId,
            'permission_id' => $permId,
            'granted_by'    => $grantedBy,
            'granted_at'    => $now,
        ], $permissionIds);

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('role_permissions')->insertOrIgnore($chunk);
        }
    }
}
