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
        $tenantId = SeederConstants::TENANT_ID;
        $now      = now();

        $users = [
            [
                'id'        => SeederConstants::USER_SUPER_ADMIN,
                'full_name' => 'Rizky Kurniawan',
                'email'     => 'rizky@majujaya.co.id',
                'role_id'   => SeederConstants::ROLE_SUPER_ADMIN,
                'is_super'  => true,
            ],
            [
                'id'        => SeederConstants::USER_HR_MANAGER,
                'full_name' => 'Sari Andini',
                'email'     => 'sari@majujaya.co.id',
                'role_id'   => SeederConstants::ROLE_HR_MANAGER,
                'is_super'  => false,
            ],
            [
                'id'        => SeederConstants::USER_FINANCE_MANAGER,
                'full_name' => 'Maya Rahayu',
                'email'     => 'maya@majujaya.co.id',
                'role_id'   => SeederConstants::ROLE_FINANCE_MANAGER,
                'is_super'  => false,
            ],
            [
                'id'        => SeederConstants::USER_SALES_MANAGER,
                'full_name' => 'Dewi Wulandari',
                'email'     => 'dewi@majujaya.co.id',
                'role_id'   => SeederConstants::ROLE_SALES_MANAGER,
                'is_super'  => false,
            ],
            [
                'id'        => SeederConstants::USER_PROJECT_MANAGER,
                'full_name' => 'Ahmad Hidayat',
                'email'     => 'ahmad@majujaya.co.id',
                'role_id'   => SeederConstants::ROLE_PROJECT_MANAGER,
                'is_super'  => false,
            ],
            [
                'id'        => SeederConstants::USER_STAFF_1,
                'full_name' => 'Budi Santoso',
                'email'     => 'budi@majujaya.co.id',
                'role_id'   => SeederConstants::ROLE_STAFF,
                'is_super'  => false,
            ],
            [
                'id'        => SeederConstants::USER_STAFF_2,
                'full_name' => 'Fauzan Hidayat',
                'email'     => 'fauzan@majujaya.co.id',
                'role_id'   => SeederConstants::ROLE_STAFF,
                'is_super'  => false,
            ],
        ];

        foreach ($users as $u) {
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

            DB::table('user_roles')->insertOrIgnore([
                'id'          => (string) Str::uuid(),
                'user_id'     => $u['id'],
                'role_id'     => $u['role_id'],
                'assigned_by' => $u['is_super'] ? null : SeederConstants::USER_SUPER_ADMIN,
                'assigned_at' => $now,
            ]);
        }

        // Update granted_by setelah Super Admin sudah ada
        DB::table('role_permissions')
            ->whereNull('granted_by')
            ->update(['granted_by' => SeederConstants::USER_SUPER_ADMIN]);

        $this->command->info('✓ ' . count($users) . ' users seeded (password: "password").');
    }
}
