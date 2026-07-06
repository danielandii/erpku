<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        DB::table('tenants')->insertOrIgnore([
            'id'                => $tenantId,
            'name'              => 'PT Maju Jaya Bersama',
            'slug'              => 'majujaya',
            'email'             => 'info@majujaya.co.id',
            'phone'             => '+62-21-12345678',
            'address'           => 'Jl. Gatot Subroto No. 88, Jakarta Selatan 12780',
            'npwp'              => '12.345.678.9-012.000',
            'timezone'          => 'Asia/Jakarta',
            'currency'          => 'IDR',
            'is_active'         => true,
            'subscription_plan' => 'pro',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Aktifkan semua modul untuk tenant demo
        $modules = ['user', 'hrd', 'marketing', 'finance', 'project'];
        foreach ($modules as $module) {
            DB::table('tenant_module_configs')->insertOrIgnore([
                'id'          => Str::uuid(),
                'tenant_id'   => $tenantId,
                'module_name' => $module,
                'is_enabled'  => true,
                'enabled_at'  => now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $this->command->info("✓ Tenant 'PT Maju Jaya Bersama' seeded.");
    }
}
