<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DepartmentPositionSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = SeederConstants::TENANT_ID;
        $now      = now();

        // ── Departments ───────────────────────────────────────────────────────
        $departments = [
            ['id' => SeederConstants::DEPT_IT,         'name' => 'Information Technology',    'code' => 'IT'],
            ['id' => SeederConstants::DEPT_HRD,        'name' => 'Human Resource & Development','code' => 'HRD'],
            ['id' => SeederConstants::DEPT_FINANCE,    'name' => 'Finance & Accounting',       'code' => 'FIN'],
            ['id' => SeederConstants::DEPT_MARKETING,  'name' => 'Marketing & Sales',          'code' => 'MKT'],
            ['id' => SeederConstants::DEPT_OPERATIONS, 'name' => 'Operations',                 'code' => 'OPS'],
            ['id' => SeederConstants::DEPT_PROJECT,    'name' => 'Project Management Office',  'code' => 'PMO'],
        ];

        foreach ($departments as $dept) {
            DB::table('departments')->insertOrIgnore(array_merge($dept, [
                'tenant_id'  => $tenantId,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // ── Work Schedules ────────────────────────────────────────────────────
        $schedules = [
            [
                'id'                     => SeederConstants::SCHED_REGULAR,
                'name'                   => 'Reguler (Senin-Jumat)',
                'work_days'              => json_encode([1,2,3,4,5]),
                'check_in_time'          => '08:00:00',
                'check_out_time'         => '17:00:00',
                'grace_period_minutes'   => 15,
                'break_duration_minutes' => 60,
            ],
            [
                'id'                     => SeederConstants::SCHED_SHIFT_PAGI,
                'name'                   => 'Shift Pagi (06:00-14:00)',
                'work_days'              => json_encode([1,2,3,4,5,6]),
                'check_in_time'          => '06:00:00',
                'check_out_time'         => '14:00:00',
                'grace_period_minutes'   => 10,
                'break_duration_minutes' => 30,
            ],
            [
                'id'                     => SeederConstants::SCHED_SHIFT_MALAM,
                'name'                   => 'Shift Malam (22:00-06:00)',
                'work_days'              => json_encode([1,2,3,4,5,6]),
                'check_in_time'          => '22:00:00',
                'check_out_time'         => '06:00:00',
                'grace_period_minutes'   => 10,
                'break_duration_minutes' => 30,
            ],
        ];

        foreach ($schedules as $sched) {
            DB::table('work_schedules')->insertOrIgnore(array_merge($sched, [
                'tenant_id'  => $tenantId,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // ── Positions ─────────────────────────────────────────────────────────
        $positions = [
            // IT
            ['id' => SeederConstants::POS_IT_DIRECTOR,  'dept' => SeederConstants::DEPT_IT,        'name' => 'IT Director',         'level' => 'director'],
            ['id' => SeederConstants::POS_IT_DEV,        'dept' => SeederConstants::DEPT_IT,        'name' => 'Software Developer',  'level' => 'staff'],
            ['id' => SeederConstants::POS_IT_SYSADMIN,   'dept' => SeederConstants::DEPT_IT,        'name' => 'System Administrator','level' => 'staff'],
            // HRD
            ['id' => SeederConstants::POS_HR_MANAGER,    'dept' => SeederConstants::DEPT_HRD,       'name' => 'HR Manager',          'level' => 'manager'],
            ['id' => SeederConstants::POS_HR_STAFF,      'dept' => SeederConstants::DEPT_HRD,       'name' => 'HR Staff',            'level' => 'staff'],
            ['id' => SeederConstants::POS_HR_RECRUITMENT,'dept' => SeederConstants::DEPT_HRD,       'name' => 'Recruitment Specialist','level' => 'staff'],
            // Finance
            ['id' => SeederConstants::POS_FIN_MANAGER,   'dept' => SeederConstants::DEPT_FINANCE,   'name' => 'Finance Manager',     'level' => 'manager'],
            ['id' => SeederConstants::POS_ACCOUNTANT,    'dept' => SeederConstants::DEPT_FINANCE,   'name' => 'Accountant',          'level' => 'staff'],
            ['id' => SeederConstants::POS_TAX,           'dept' => SeederConstants::DEPT_FINANCE,   'name' => 'Tax Specialist',      'level' => 'staff'],
            // Marketing
            ['id' => SeederConstants::POS_SALES_MANAGER, 'dept' => SeederConstants::DEPT_MARKETING, 'name' => 'Sales Manager',       'level' => 'manager'],
            ['id' => SeederConstants::POS_SALES_EXEC,    'dept' => SeederConstants::DEPT_MARKETING, 'name' => 'Sales Executive',     'level' => 'staff'],
            ['id' => SeederConstants::POS_MARKETING,     'dept' => SeederConstants::DEPT_MARKETING, 'name' => 'Marketing Specialist','level' => 'staff'],
            // Project
            ['id' => SeederConstants::POS_PM,            'dept' => SeederConstants::DEPT_PROJECT,   'name' => 'Project Manager',     'level' => 'manager'],
            ['id' => SeederConstants::POS_BUSINESS_ANALYST,'dept'=> SeederConstants::DEPT_PROJECT,  'name' => 'Business Analyst',    'level' => 'staff'],
        ];

        foreach ($positions as $pos) {
            DB::table('positions')->insertOrIgnore([
                'id'            => $pos['id'],
                'tenant_id'     => $tenantId,
                'department_id' => $pos['dept'],
                'name'          => $pos['name'],
                'level'         => $pos['level'],
                'is_active'     => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        $this->command->info('✓ Departments, work schedules, and positions seeded.');
    }
}
