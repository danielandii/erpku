<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,          // 1. Global permissions
            TenantSeeder::class,              // 2. Tenant + module configs
            RoleSeeder::class,                // 3. Roles + assign permissions (granted_by=null)
            UserSeeder::class,                // 4. Users + user_roles + update granted_by
            DepartmentPositionSeeder::class,  // 5. Dept, positions, work schedules
            EmployeeSeeder::class,            // 6. Employees + salary + leave allocations
            LeaveTypeSeeder::class,           // 7. Leave types
            ChartOfAccountSeeder::class,      // 8. COA (70+ akun)
            LeadStageSeeder::class,           // 9. Lead pipeline stages
        ]);
    }
}
