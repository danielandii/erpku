<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. Data global: permissions
            PermissionSeeder::class,

            // 2. Tenant demo
            TenantSeeder::class,

            // 3. Roles (tanpa granted_by dulu — user belum ada)
            RoleSeeder::class,

            // 4. Users + assign roles + update granted_by di role_permissions
            UserSeeder::class,

            // 5. HRD
            DepartmentPositionSeeder::class,
            EmployeeSeeder::class,
            LeaveTypeSeeder::class,

            // 6. Finance
            ChartOfAccountSeeder::class,

            // 7. Marketing
            LeadStageSeeder::class,
        ]);
    }
}
