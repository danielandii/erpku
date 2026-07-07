<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $now      = now();

        $users = [
            [
                'id'        => 'user-super-admin-00000000000001',
                'full_name' => 'Rizky Kurniawan',
                'email'     => 'rizky@majujaya.co.id',
                'role_id'   => 'role-super-admin-000000000001',
                'is_super'  => true,
            ],
            [
                'id'        => 'user-hr-manager-000000000000002',
                'full_name' => 'Sari Andini',
                'email'     => 'sari@majujaya.co.id',
                'role_id'   => 'role-hr-manager-00000000000003',
                'is_super'  => false,
            ],
            [
                'id'        => 'user-finance-manager-00000000003',
                'full_name' => 'Maya Rahayu',
                'email'     => 'maya@majujaya.co.id',
                'role_id'   => 'role-finance-manager-000000004',
                'is_super'  => false,
            ],
            [
                'id'        => 'user-sales-manager-000000000004',
                'full_name' => 'Dewi Wulandari',
                'email'     => 'dewi@majujaya.co.id',
                'role_id'   => 'role-sales-manager-0000000005',
                'is_super'  => false,
            ],
            [
                'id'        => 'user-project-manager-0000000005',
                'full_name' => 'Ahmad Hidayat',
                'email'     => 'ahmad@majujaya.co.id',
                'role_id'   => 'role-project-manager-000000006',
                'is_super'  => false,
            ],
            [
                'id'        => 'user-staff-finance-00000000000006',
                'full_name' => 'Budi Santoso',
                'email'     => 'budi@majujaya.co.id',
                'role_id'   => 'role-staff-general-0000000007',
                'is_super'  => false,
            ],
            [
                'id'        => 'user-staff-sales-000000000000007',
                'full_name' => 'Fauzan Hidayat',
                'email'     => 'fauzan@majujaya.co.id',
                'role_id'   => 'role-staff-general-0000000007',
                'is_super'  => false,
            ],
        ];

        foreach ($users as $u) {
            // 1. Insert user
            DB::table('users')->insertOrIgnore([
                'id'                 => $u['id'],
                'tenant_id'          => $tenantId,
                'full_name'          => $u['full_name'],
                'email'              => $u['email'],
                'email_verified_at'  => $now,
                'password'           => Hash::make('password'),
                'is_active'          => true,
                'is_super_admin'     => $u['is_super'],
                'two_factor_enabled' => false,
                'failed_login_count' => 0,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            // 2. Assign role ke user
            // assigned_by = null untuk Super Admin (self-assign),
            // atau = Super Admin ID untuk user lainnya.
            // Karena kolom sudah nullable, ini tidak akan error FK.
            $assignedBy = $u['is_super']
                ? null                              // Super Admin: tidak ada yang assign
                : 'user-super-admin-00000000000001'; // User lain: di-assign oleh Super Admin

            DB::table('user_roles')->insertOrIgnore([
                'id'          => (string) Str::uuid(),
                'user_id'     => $u['id'],
                'role_id'     => $u['role_id'],
                'assigned_by' => $assignedBy,
                'assigned_at' => $now,
            ]);
        }

        // Update granted_by di role_permissions setelah Super Admin user sudah ada
        // Isi granted_by yang tadinya null dengan Super Admin ID
        DB::table('role_permissions')
            ->whereNull('granted_by')
            ->update(['granted_by' => 'user-super-admin-00000000000001']);

        $this->command->info('✓ ' . count($users) . ' users seeded (password: "password").');
        $this->command->info('✓ granted_by di role_permissions diperbarui ke Super Admin.');
    }
}
