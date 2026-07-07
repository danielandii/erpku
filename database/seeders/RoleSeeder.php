<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = SeederConstants::TENANT_ID;
        $now      = now();

        $roles = [
            [
                'id'          => SeederConstants::ROLE_SUPER_ADMIN,
                'tenant_id'   => null,
                'name'        => 'Super Admin',
                'slug'        => 'super_admin',
                'description' => 'Akses penuh ke seluruh sistem termasuk pengaturan tenant',
                'is_system'   => true,
            ],
            [
                'id'          => SeederConstants::ROLE_ADMIN,
                'tenant_id'   => $tenantId,
                'name'        => 'Admin',
                'slug'        => 'admin',
                'description' => 'Admin perusahaan — kelola user, modul, dan konfigurasi',
                'is_system'   => true,
            ],
            [
                'id'          => SeederConstants::ROLE_HR_MANAGER,
                'tenant_id'   => $tenantId,
                'name'        => 'HR Manager',
                'slug'        => 'hr_manager',
                'description' => 'Kelola seluruh modul HRD: karyawan, presensi, cuti, gaji',
                'is_system'   => true,
            ],
            [
                'id'          => SeederConstants::ROLE_FINANCE_MANAGER,
                'tenant_id'   => $tenantId,
                'name'        => 'Finance Manager',
                'slug'        => 'finance_manager',
                'description' => 'Kelola invoice, pembayaran, jurnal, dan laporan keuangan',
                'is_system'   => true,
            ],
            [
                'id'          => SeederConstants::ROLE_SALES_MANAGER,
                'tenant_id'   => $tenantId,
                'name'        => 'Sales Manager',
                'slug'        => 'sales_manager',
                'description' => 'Kelola leads, quotation, dan klien',
                'is_system'   => true,
            ],
            [
                'id'          => SeederConstants::ROLE_PROJECT_MANAGER,
                'tenant_id'   => $tenantId,
                'name'        => 'Project Manager',
                'slug'        => 'project_manager',
                'description' => 'Kelola proyek, task, dan anggota tim',
                'is_system'   => true,
            ],
            [
                'id'          => SeederConstants::ROLE_STAFF,
                'tenant_id'   => $tenantId,
                'name'        => 'Staff',
                'slug'        => 'staff',
                'description' => 'Akses dasar: presensi, cuti, lihat task yang di-assign',
                'is_system'   => true,
            ],
            [
                'id'          => SeederConstants::ROLE_VIEWER,
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

        // Assign permissions ke roles
        $allPerms = DB::table('permissions')->pluck('id', 'name');

        $this->assignPermissions(SeederConstants::ROLE_SUPER_ADMIN,
            $allPerms->values()->toArray()
        );

        $this->assignPermissions(SeederConstants::ROLE_ADMIN,
            $allPerms->filter(fn($id, $name) => str_starts_with($name, 'user.'))->values()->toArray()
        );

        $this->assignPermissions(SeederConstants::ROLE_HR_MANAGER,
            $allPerms->filter(fn($id, $name) =>
                str_starts_with($name, 'hrd.') || $name === 'user.users.read'
            )->values()->toArray()
        );

        $this->assignPermissions(SeederConstants::ROLE_FINANCE_MANAGER,
            $allPerms->filter(fn($id, $name) =>
                str_starts_with($name, 'finance.') ||
                in_array($name, ['marketing.clients.read', 'marketing.quotations.read', 'user.users.read'])
            )->values()->toArray()
        );

        $this->assignPermissions(SeederConstants::ROLE_SALES_MANAGER,
            $allPerms->filter(fn($id, $name) => str_starts_with($name, 'marketing.'))->values()->toArray()
        );

        $this->assignPermissions(SeederConstants::ROLE_PROJECT_MANAGER,
            $allPerms->filter(fn($id, $name) => str_starts_with($name, 'project.'))->values()->toArray()
        );

        $this->assignPermissions(SeederConstants::ROLE_STAFF,
            $allPerms->filter(fn($id, $name) => in_array($name, [
                'hrd.attendance.create', 'hrd.attendance.read',
                'hrd.leave.create', 'hrd.leave.read',
                'project.tasks.read', 'project.tasks.update',
                'project.time_logs.create', 'project.time_logs.read',
            ]))->values()->toArray()
        );

        $this->assignPermissions(SeederConstants::ROLE_VIEWER,
            $allPerms->filter(fn($id, $name) => str_ends_with($name, '.read'))->values()->toArray()
        );

        $this->command->info('✓ Roles & permissions seeded.');
    }

    private function assignPermissions(string $roleId, array $permissionIds): void
    {
        if (empty($permissionIds)) return;
        $now  = now();
        $rows = array_map(fn($permId) => [
            'id'            => (string) Str::uuid(),
            'role_id'       => $roleId,
            'permission_id' => $permId,
            'granted_by'    => null,
            'granted_at'    => $now,
        ], $permissionIds);
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('role_permissions')->insertOrIgnore($chunk);
        }
    }
}
