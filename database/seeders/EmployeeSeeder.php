<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $now      = now();

        $employees = [
            [
                'id'              => 'emp-rizky-000000000000000001',
                'user_id'         => '22222222-2222-2222-2222-00000000000001',
                'employee_number' => 'MJ-001',
                'full_name'       => 'Rizky Kurniawan',
                'gender'          => 'male',
                'marital_status'  => 'married',
                'num_dependants'  => 'K1',
                'department_id'   => 'dept-it-00000000000000000001',
                'position_id'     => 'pos-it-director-000000000001',
                'join_date'       => '2019-01-02',
                'employment_type' => 'permanent',
                'base_salary'     => 15000000,
            ],
            [
                'id'              => 'emp-sari-000000000000000000002',
                'user_id'         => '22222222-2222-2222-2222-00000000000002',
                'employee_number' => 'MJ-002',
                'full_name'       => 'Sari Andini',
                'gender'          => 'female',
                'marital_status'  => 'married',
                'num_dependants'  => 'K0',
                'department_id'   => 'dept-hrd-0000000000000000002',
                'position_id'     => 'pos-hr-manager-000000000004',
                'join_date'       => '2020-01-06',
                'employment_type' => 'permanent',
                'base_salary'     => 8000000,
            ],
            [
                'id'              => 'emp-maya-000000000000000000003',
                'user_id'         => '22222222-2222-2222-2222-00000000000003',
                'employee_number' => 'MJ-003',
                'full_name'       => 'Maya Rahayu',
                'gender'          => 'female',
                'marital_status'  => 'single',
                'num_dependants'  => 'TK0',
                'department_id'   => 'dept-fin-0000000000000000003',
                'position_id'     => 'pos-fin-manager-00000000000007',
                'join_date'       => '2019-03-01',
                'employment_type' => 'permanent',
                'base_salary'     => 9000000,
            ],
            [
                'id'              => 'emp-dewi-000000000000000000004',
                'user_id'         => '22222222-2222-2222-2222-000000000004',
                'employee_number' => 'MJ-004',
                'full_name'       => 'Dewi Wulandari',
                'gender'          => 'female',
                'marital_status'  => 'married',
                'num_dependants'  => 'K2',
                'department_id'   => 'dept-mkt-0000000000000000004',
                'position_id'     => 'pos-sales-manager-0000000000010',
                'join_date'       => '2021-06-01',
                'employment_type' => 'permanent',
                'base_salary'     => 7500000,
            ],
            [
                'id'              => 'emp-ahmad-00000000000000000005',
                'user_id'         => '22222222-2222-2222-2222-00000000000005',
                'employee_number' => 'MJ-005',
                'full_name'       => 'Ahmad Hidayat',
                'gender'          => 'male',
                'marital_status'  => 'married',
                'num_dependants'  => 'K1',
                'department_id'   => 'dept-prj-0000000000000000006',
                'position_id'     => 'pos-pm-000000000000000000013',
                'join_date'       => '2020-07-01',
                'employment_type' => 'permanent',
                'base_salary'     => 8500000,
            ],
            [
                'id'              => 'emp-budi-000000000000000000006',
                'user_id'         => '22222222-2222-2222-2222-00000000000006',
                'employee_number' => 'MJ-006',
                'full_name'       => 'Budi Santoso',
                'gender'          => 'male',
                'marital_status'  => 'married',
                'num_dependants'  => 'K0',
                'department_id'   => 'dept-fin-0000000000000000003',
                'position_id'     => 'pos-accountant-0000000000000008',
                'join_date'       => '2022-03-14',
                'employment_type' => 'permanent',
                'base_salary'     => 5500000,
            ],
            [
                'id'              => 'emp-fauzan-0000000000000000007',
                'user_id'         => '22222222-2222-2222-2222-000000000000007',
                'employee_number' => 'MJ-007',
                'full_name'       => 'Fauzan Hidayat',
                'gender'          => 'male',
                'marital_status'  => 'single',
                'num_dependants'  => 'TK0',
                'department_id'   => 'dept-mkt-0000000000000000004',
                'position_id'     => 'pos-sales-exec-000000000000011',
                'join_date'       => '2023-09-01',
                'employment_type' => 'contract',
                'contract_end_date'=> '2024-08-31',
                'base_salary'     => 4500000,
            ],
        ];

        foreach ($employees as $emp) {
            $baseSalary = $emp['base_salary'];
            unset($emp['base_salary']);

            DB::table('employees')->insertOrIgnore(array_merge($emp, [
                'tenant_id'          => $tenantId,
                'work_schedule_id'   => 'sched-reguler-000000000001',
                'bank_name'          => 'BCA',
                'bank_account'       => '1234' . rand(100000, 999999),
                'bank_account_name'  => $emp['full_name'],
                'is_active'          => true,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]));

            // Insert salary structure
            DB::table('salary_structures')->insertOrIgnore([
                'id'             => Str::uuid(),
                'tenant_id'      => $tenantId,
                'employee_id'    => $emp['id'],
                'base_salary'    => $baseSalary,
                'effective_date' => $emp['join_date'],
                'components'     => json_encode([
                    ['name' => 'Tunjangan Jabatan',   'type' => 'allowance', 'amount' => round($baseSalary * 0.1),  'is_taxable' => true,  'is_fixed' => true],
                    ['name' => 'Tunjangan Transport',  'type' => 'allowance', 'amount' => 500000,                    'is_taxable' => false, 'is_fixed' => true],
                    ['name' => 'Tunjangan Makan',     'type' => 'allowance', 'amount' => 400000,                    'is_taxable' => false, 'is_fixed' => true],
                    ['name' => 'BPJS Kesehatan (4%)', 'type' => 'employer',  'amount' => round($baseSalary * 0.04), 'is_taxable' => false, 'is_fixed' => true],
                    ['name' => 'BPJS TK JHT (3.7%)', 'type' => 'employer',  'amount' => round($baseSalary * 0.037),'is_taxable' => false, 'is_fixed' => true],
                ]),
                'created_by'     => '22222222-2222-2222-2222-00000000000001',
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }

        // Update employee_id di tabel users
        foreach ($employees as $emp) {
            DB::table('users')
                ->where('id', $emp['user_id'])
                ->update(['employee_id' => $emp['id']]);
        }

        // Set manager_id di departments
        DB::table('departments')->where('id', 'dept-hrd-0000000000000000002')
            ->update(['manager_id' => 'emp-sari-000000000000000000002']);
        DB::table('departments')->where('id', 'dept-fin-0000000000000000003')
            ->update(['manager_id' => 'emp-maya-000000000000000000003']);
        DB::table('departments')->where('id', 'dept-mkt-0000000000000000004')
            ->update(['manager_id' => 'emp-dewi-000000000000000000004']);
        DB::table('departments')->where('id', 'dept-prj-0000000000000000006')
            ->update(['manager_id' => 'emp-ahmad-00000000000000000005']);
        DB::table('departments')->where('id', 'dept-it-00000000000000000001')
            ->update(['manager_id' => 'emp-rizky-000000000000000001']);

        // Isi leave_allocations tahun 2024
        $this->seedLeaveAllocations($tenantId, $employees, $now);

        $this->command->info('✓ ' . count($employees) . ' employees seeded with salary structures.');
    }

    private function seedLeaveAllocations(string $tenantId, array $employees, $now): void
    {
        $leaveTypes = DB::table('leave_types')
            ->where('tenant_id', $tenantId)
            ->pluck('id', 'code');

        if ($leaveTypes->isEmpty()) {
            return; // LeaveTypeSeeder belum jalan
        }

        foreach ($employees as $emp) {
            if (isset($leaveTypes['ANNUAL'])) {
                DB::table('leave_allocations')->insertOrIgnore([
                    'id'             => Str::uuid(),
                    'tenant_id'      => $tenantId,
                    'employee_id'    => $emp['id'],
                    'leave_type_id'  => $leaveTypes['ANNUAL'],
                    'year'           => 2024,
                    'allocated_days' => 12,
                    'used_days'      => 0,
                    'pending_days'   => 0,
                    'carry_over_days'=> 0,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
            if (isset($leaveTypes['SICK'])) {
                DB::table('leave_allocations')->insertOrIgnore([
                    'id'             => Str::uuid(),
                    'tenant_id'      => $tenantId,
                    'employee_id'    => $emp['id'],
                    'leave_type_id'  => $leaveTypes['SICK'],
                    'year'           => 2024,
                    'allocated_days' => 12,
                    'used_days'      => 0,
                    'pending_days'   => 0,
                    'carry_over_days'=> 0,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }
    }
}
