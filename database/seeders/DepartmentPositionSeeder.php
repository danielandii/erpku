<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DepartmentPositionSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $now      = now();

        // ── Departments ────────────────────────────────────────
        $departments = [
            ['id' => 'dept-it-00000000000000000001', 'name' => 'Information Technology', 'code' => 'IT'],
            ['id' => 'dept-hrd-0000000000000000002', 'name' => 'Human Resource & Development', 'code' => 'HRD'],
            ['id' => 'dept-fin-0000000000000000003', 'name' => 'Finance & Accounting', 'code' => 'FIN'],
            ['id' => 'dept-mkt-0000000000000000004', 'name' => 'Marketing & Sales', 'code' => 'MKT'],
            ['id' => 'dept-ops-0000000000000000005', 'name' => 'Operations', 'code' => 'OPS'],
            ['id' => 'dept-prj-0000000000000000006', 'name' => 'Project Management Office', 'code' => 'PMO'],
        ];

        foreach ($departments as $dept) {
            DB::table('departments')->insertOrIgnore(array_merge($dept, [
                'tenant_id'  => $tenantId,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // ── Work Schedules ─────────────────────────────────────
        $schedules = [
            [
                'id'                    => 'sched-reguler-000000000001',
                'name'                  => 'Reguler (Senin-Jumat)',
                'work_days'             => json_encode([1, 2, 3, 4, 5]),
                'check_in_time'         => '08:00:00',
                'check_out_time'        => '17:00:00',
                'grace_period_minutes'  => 15,
                'break_duration_minutes'=> 60,
            ],
            [
                'id'                    => 'sched-shift-pagi-0000000002',
                'name'                  => 'Shift Pagi (06:00-14:00)',
                'work_days'             => json_encode([1, 2, 3, 4, 5, 6]),
                'check_in_time'         => '06:00:00',
                'check_out_time'        => '14:00:00',
                'grace_period_minutes'  => 10,
                'break_duration_minutes'=> 30,
            ],
            [
                'id'                    => 'sched-shift-malam-000000003',
                'name'                  => 'Shift Malam (22:00-06:00)',
                'work_days'             => json_encode([1, 2, 3, 4, 5, 6]),
                'check_in_time'         => '22:00:00',
                'check_out_time'        => '06:00:00',
                'grace_period_minutes'  => 10,
                'break_duration_minutes'=> 30,
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

        // ── Positions ──────────────────────────────────────────
        $positions = [
            // IT
            ['id' => 'pos-it-director-000000000001', 'dept' => 'dept-it-00000000000000000001', 'name' => 'IT Director',       'level' => 'Director'],
            ['id' => 'pos-fullstack-dev-0000000002', 'dept' => 'dept-it-00000000000000000001', 'name' => 'Fullstack Developer','level' => 'Staff'],
            ['id' => 'pos-devops-eng-00000000000003','dept' => 'dept-it-00000000000000000001', 'name' => 'DevOps Engineer',   'level' => 'Staff'],
            // HRD
            ['id' => 'pos-hr-manager-000000000004',  'dept' => 'dept-hrd-0000000000000000002', 'name' => 'HR Manager',        'level' => 'Manager'],
            ['id' => 'pos-hr-staff-0000000000000005','dept' => 'dept-hrd-0000000000000000002', 'name' => 'HR Staff',          'level' => 'Staff'],
            ['id' => 'pos-recruitment-00000000000006','dept'=> 'dept-hrd-0000000000000000002', 'name' => 'Recruitment Staff', 'level' => 'Staff'],
            // Finance
            ['id' => 'pos-fin-manager-00000000000007','dept'=> 'dept-fin-0000000000000000003', 'name' => 'Finance Manager',   'level' => 'Manager'],
            ['id' => 'pos-accountant-0000000000000008','dept'=>'dept-fin-0000000000000000003', 'name' => 'Accountant',        'level' => 'Staff'],
            ['id' => 'pos-tax-staff-000000000000009', 'dept'=>'dept-fin-0000000000000000003', 'name' => 'Tax Staff',         'level' => 'Staff'],
            // Marketing
            ['id' => 'pos-sales-manager-0000000000010','dept'=>'dept-mkt-0000000000000000004','name' => 'Sales Manager',      'level' => 'Manager'],
            ['id' => 'pos-sales-exec-000000000000011', 'dept'=>'dept-mkt-0000000000000000004','name' => 'Sales Executive',    'level' => 'Staff'],
            ['id' => 'pos-mkt-specialist-000000000012','dept'=>'dept-mkt-0000000000000000004','name' => 'Marketing Specialist','level' => 'Staff'],
            // PMO
            ['id' => 'pos-pm-000000000000000000013',  'dept'=>'dept-prj-0000000000000000006', 'name' => 'Project Manager',   'level' => 'Manager'],
            ['id' => 'pos-ba-000000000000000000014',  'dept'=>'dept-prj-0000000000000000006', 'name' => 'Business Analyst',  'level' => 'Staff'],
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

        // ── Public Holidays 2024 ───────────────────────────────
        $holidays = [
            ['name' => "Tahun Baru 2024",          'date' => '2024-01-01'],
            ['name' => "Isra Mi'raj",               'date' => '2024-02-08'],
            ['name' => "Hari Raya Nyepi",           'date' => '2024-03-11'],
            ['name' => "Jumat Agung",               'date' => '2024-03-29'],
            ['name' => "Idul Fitri 1445 H (H-1)",  'date' => '2024-04-10'],
            ['name' => "Idul Fitri 1445 H",        'date' => '2024-04-11'],
            ['name' => "Idul Fitri 1445 H (H+1)",  'date' => '2024-04-12'],
            ['name' => "Hari Buruh Nasional",       'date' => '2024-05-01'],
            ['name' => "Kenaikan Isa Almasih",      'date' => '2024-05-09'],
            ['name' => "Hari Kebangkitan Nasional", 'date' => '2024-05-20'],
            ['name' => "Waisak 2568 BE",            'date' => '2024-05-23'],
            ['name' => "Idul Adha 1445 H",         'date' => '2024-06-17'],
            ['name' => "Tahun Baru Islam 1446 H",  'date' => '2024-07-07'],
            ['name' => "Hari Kemerdekaan RI",       'date' => '2024-08-17'],
            ['name' => "Maulid Nabi Muhammad SAW",  'date' => '2024-09-16'],
            ['name' => "Hari Natal",                'date' => '2024-12-25'],
        ];

        foreach ($holidays as $h) {
            DB::table('public_holidays')->insertOrIgnore([
                'id'           => Str::uuid(),
                'tenant_id'    => $tenantId,
                'name'         => $h['name'],
                'holiday_date' => $h['date'],
                'is_recurring' => false,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        $this->command->info('✓ Departments, positions, work schedules & holidays seeded.');
    }
}
