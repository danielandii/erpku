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

        // ── System Roles ───────────────────────────────────────────────────────
        $roles = [
            [
                'id'          => 'role-super-admin-000000000001',
                'tenant_id'   => null,
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

        // ── Assign Permissions ke Roles ────────────────────────────────────────
        $allPerms = DB::table('permissions')->pluck('id', 'name');

        // Super Admin: semua permission
        $this->assignPermissions(
            'role-super-admin-000000000001',
            $allPerms->values()->toArray()
        );

        // HR Manager: semua HRD + read user
        $this->assignPermissions(
            'role-hr-manager-00000000000003',
            $allPerms->filter(fn($id, $name) =>
                str_starts_with($name, 'hrd.') ||
                in_array($name, ['user.users.read'])
            )->values()->toArray()
        );

        // Finance Manager: semua Finance + read marketing tertentu + read users
        $this->assignPermissions(
            'role-finance-manager-000000004',
            $allPerms->filter(fn($id, $name) =>
                str_starts_with($name, 'finance.') ||
                in_array($name, [
                    'marketing.clients.read',
                    'marketing.quotations.read',
                    'user.users.read',
                ])
            )->values()->toArray()
        );

        // Sales Manager: semua Marketing
        $this->assignPermissions(
            'role-sales-manager-0000000005',
            $allPerms->filter(fn($id, $name) =>
                str_starts_with($name, 'marketing.')
            )->values()->toArray()
        );

        // Project Manager: semua Project
        $this->assignPermissions(
            'role-project-manager-000000006',
            $allPerms->filter(fn($id, $name) =>
                str_starts_with($name, 'project.')
            )->values()->toArray()
        );

        // Staff: permission terbatas
        $this->assignPermissions(
            'role-staff-general-0000000007',
            $allPerms->filter(fn($id, $name) =>
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
            )->values()->toArray()
        );

        // Viewer: hanya read
        $this->assignPermissions(
            'role-viewer-readonly-000000008',
            $allPerms->filter(fn($id, $name) =>
                str_ends_with($name, '.read')
            )->values()->toArray()
        );

        $this->command->info('✓ Roles & permissions seeded.');
    }

    private function assignPermissions(string $roleId, array $permissionIds): void
    {
        if (empty($permissionIds)) return;

        $now = now();

        $rows = array_map(fn($permId) => [
            'id'            => (string) Str::uuid(),
            'role_id'       => $roleId,
            'permission_id' => $permId,
            // granted_by = null karena kolom sudah nullable
            // (FK ke users tidak bisa diisi sebelum UserSeeder jalan)
            'granted_by'    => null,
            'granted_at'    => $now,
        ], $permissionIds);

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('role_permissions')->insertOrIgnore($chunk);
        }
    }
}
