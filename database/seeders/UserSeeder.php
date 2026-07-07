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
                'id'        => '22222222-2222-2222-2222-000000000001',
                'full_name' => 'Rizky Kurniawan',
                'email'     => 'rizky@majujaya.co.id',
                'role_id'   => '11111111-1111-1111-1111-000000000001',
            ],
            [
                'id'        => '22222222-2222-2222-2222-000000000002',
                'full_name' => 'Sari Andini',
                'email'     => 'sari@majujaya.co.id',
                'role_id'   => '11111111-1111-1111-1111-000000000003',
            ],
            [
                'id'        => '22222222-2222-2222-2222-000000000003',
                'full_name' => 'Maya Rahayu',
                'email'     => 'maya@majujaya.co.id',
                'role_id'   => '11111111-1111-1111-1111-000000000004',
            ],
            [
                'id'        => '22222222-2222-2222-2222-000000000004',
                'full_name' => 'Dewi Wulandari',
                'email'     => 'dewi@majujaya.co.id',
                'role_id'   => '11111111-1111-1111-1111-000000000005',
            ],
            [
                'id'        => '22222222-2222-2222-2222-000000000005',
                'full_name' => 'Ahmad Hidayat',
                'email'     => 'ahmad@majujaya.co.id',
                'role_id'   => '11111111-1111-1111-1111-000000000006',
            ],
            [
                'id'        => '22222222-2222-2222-2222-000000000006',
                'full_name' => 'Budi Santoso',
                'email'     => 'budi@majujaya.co.id',
                'role_id'   => '11111111-1111-1111-1111-000000000007',
            ],
            [
                'id'        => '22222222-2222-2222-2222-000000000007',
                'full_name' => 'Fauzan Hidayat',
                'email'     => 'fauzan@majujaya.co.id',
                'role_id'   => '11111111-1111-1111-1111-000000000007',
            ],
        ];

        foreach ($users as $u) {
            DB::table('users')->insertOrIgnore([
                'id'                  => $u['id'],
                'tenant_id'           => $tenantId,
                'full_name'           => $u['full_name'],
                'email'               => $u['email'],
                'email_verified_at'   => $now,
                'password'            => Hash::make('password'),
                'is_active'           => true,
                'is_super_admin'      => $u['role_id'] === '11111111-1111-1111-1111-000000000001',
                'two_factor_enabled'  => false,
                'failed_login_count'  => 0,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            // Assign role ke user
            DB::table('user_roles')->insertOrIgnore([
                'id'          => Str::uuid(),
                'user_id'     => $u['id'],
                'role_id'     => $u['role_id'],
                'assigned_by' => '22222222-2222-2222-2222-000000000001',
                'assigned_at' => $now,
                // 'created_at'  => $now,
                // 'updated_at'  => $now,
            ]);
        }

        $this->command->info('✓ ' . count($users) . ' users seeded (password: "password").');
    }
}
