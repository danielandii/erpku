<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. Data global sistem
            PermissionSeeder::class,

            // 2. Tenant demo
            TenantSeeder::class,

            // 3. User & RBAC
            RoleSeeder::class,
            UserSeeder::class,

            // 4. HRD
            DepartmentPositionSeeder::class,
            EmployeeSeeder::class,
            LeaveTypeSeeder::class,

            // 5. Finance
            ChartOfAccountSeeder::class,

            // 6. Marketing
            LeadStageSeeder::class,
        ]);
    }
}
