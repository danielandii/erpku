<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = SeederConstants::TENANT_ID;
        $now      = now();

        $employees = [
            [
                'id'              => SeederConstants::EMP_RIZKY,
                'user_id'         => SeederConstants::USER_SUPER_ADMIN,
                'employee_number' => 'MJ-001',
                'full_name'       => 'Rizky Kurniawan',
                'gender'          => 'male',
                'marital_status'  => 'married',
                'num_dependants'  => 'K1',
                'department_id'   => SeederConstants::DEPT_IT,
                'position_id'     => SeederConstants::POS_IT_DIRECTOR,
                'work_schedule_id'=> SeederConstants::SCHED_REGULAR,
                'join_date'       => '2019-01-02',
                'employment_type' => 'permanent',
                'base_salary'     => 15000000,
            ],
            [
                'id'              => SeederConstants::EMP_SARI,
                'user_id'         => SeederConstants::USER_HR_MANAGER,
                'employee_number' => 'MJ-002',
                'full_name'       => 'Sari Andini',
                'gender'          => 'female',
                'marital_status'  => 'married',
                'num_dependants'  => 'K0',
                'department_id'   => SeederConstants::DEPT_HRD,
                'position_id'     => SeederConstants::POS_HR_MANAGER,
                'work_schedule_id'=> SeederConstants::SCHED_REGULAR,
                'join_date'       => '2020-01-06',
                'employment_type' => 'permanent',
                'base_salary'     => 8000000,
            ],
            [
                'id'              => SeederConstants::EMP_MAYA,
                'user_id'         => SeederConstants::USER_FINANCE_MANAGER,
                'employee_number' => 'MJ-003',
                'full_name'       => 'Maya Rahayu',
                'gender'          => 'female',
                'marital_status'  => 'single',
                'num_dependants'  => 'TK0',
                'department_id'   => SeederConstants::DEPT_FINANCE,
                'position_id'     => SeederConstants::POS_FIN_MANAGER,
                'work_schedule_id'=> SeederConstants::SCHED_REGULAR,
                'join_date'       => '2019-03-01',
                'employment_type' => 'permanent',
                'base_salary'     => 9000000,
            ],
            [
                'id'              => SeederConstants::EMP_DEWI,
                'user_id'         => SeederConstants::USER_SALES_MANAGER,
                'employee_number' => 'MJ-004',
                'full_name'       => 'Dewi Wulandari',
                'gender'          => 'female',
                'marital_status'  => 'married',
                'num_dependants'  => 'K2',
                'department_id'   => SeederConstants::DEPT_MARKETING,
                'position_id'     => SeederConstants::POS_SALES_MANAGER,
                'work_schedule_id'=> SeederConstants::SCHED_REGULAR,
                'join_date'       => '2021-06-01',
                'employment_type' => 'permanent',
                'base_salary'     => 7500000,
            ],
            [
                'id'              => SeederConstants::EMP_AHMAD,
                'user_id'         => SeederConstants::USER_PROJECT_MANAGER,
                'employee_number' => 'MJ-005',
                'full_name'       => 'Ahmad Hidayat',
                'gender'          => 'male',
                'marital_status'  => 'married',
                'num_dependants'  => 'K1',
                'department_id'   => SeederConstants::DEPT_PROJECT,
                'position_id'     => SeederConstants::POS_PM,
                'work_schedule_id'=> SeederConstants::SCHED_REGULAR,
                'join_date'       => '2020-07-01',
                'employment_type' => 'permanent',
                'base_salary'     => 8500000,
            ],
            [
                'id'              => SeederConstants::EMP_BUDI,
                'user_id'         => SeederConstants::USER_STAFF_1,
                'employee_number' => 'MJ-006',
                'full_name'       => 'Budi Santoso',
                'gender'          => 'male',
                'marital_status'  => 'married',
                'num_dependants'  => 'K0',
                'department_id'   => SeederConstants::DEPT_FINANCE,
                'position_id'     => SeederConstants::POS_ACCOUNTANT,
                'work_schedule_id'=> SeederConstants::SCHED_REGULAR,
                'join_date'       => '2022-03-14',
                'employment_type' => 'permanent',
                'base_salary'     => 5500000,
            ],
            [
                'id'               => SeederConstants::EMP_FAUZAN,
                'user_id'          => SeederConstants::USER_STAFF_2,
                'employee_number'  => 'MJ-007',
                'full_name'        => 'Fauzan Hidayat',
                'gender'           => 'male',
                'marital_status'   => 'single',
                'num_dependants'   => 'TK0',
                'department_id'    => SeederConstants::DEPT_MARKETING,
                'position_id'      => SeederConstants::POS_SALES_EXEC,
                'work_schedule_id' => SeederConstants::SCHED_REGULAR,
                'join_date'        => '2023-09-01',
                'employment_type'  => 'contract',
                'contract_end_date'=> '2024-08-31',
                'base_salary'      => 4500000,
            ],
        ];

        foreach ($employees as $emp) {
            $baseSalary = $emp['base_salary'];
            unset($emp['base_salary']);

            DB::table('employees')->insertOrIgnore(array_merge($emp, [
                'tenant_id'         => $tenantId,
                'bank_name'         => 'BCA',
                'bank_account'      => '1234' . rand(100000, 999999),
                'bank_account_name' => $emp['full_name'],
                'is_active'         => true,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]));

            // Salary structure
            DB::table('salary_structures')->insertOrIgnore([
                'id'             => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'employee_id'    => $emp['id'],
                'base_salary'    => $baseSalary,
                'effective_date' => $emp['join_date'],
                'components'     => json_encode([
                    ['name' => 'Tunjangan Jabatan',   'type' => 'allowance', 'amount' => round($baseSalary * 0.10), 'is_taxable' => true,  'is_fixed' => true],
                    ['name' => 'Tunjangan Transport',  'type' => 'allowance', 'amount' => 500000,                    'is_taxable' => false, 'is_fixed' => true],
                    ['name' => 'Tunjangan Makan',     'type' => 'allowance', 'amount' => 400000,                    'is_taxable' => false, 'is_fixed' => true],
                    ['name' => 'BPJS Kes Perusahaan', 'type' => 'employer',  'amount' => round($baseSalary * 0.04), 'is_taxable' => false, 'is_fixed' => true],
                    ['name' => 'BPJS TK JHT',         'type' => 'employer',  'amount' => round($baseSalary * 0.037),'is_taxable' => false, 'is_fixed' => true],
                ]),
                'created_by'     => SeederConstants::USER_SUPER_ADMIN,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            // Update employee_id di users
            DB::table('users')->where('id', $emp['user_id'])
                ->update(['employee_id' => $emp['id']]);
        }

        // Set manager_id di departments
        $managerMap = [
            SeederConstants::DEPT_IT        => SeederConstants::EMP_RIZKY,
            SeederConstants::DEPT_HRD       => SeederConstants::EMP_SARI,
            SeederConstants::DEPT_FINANCE   => SeederConstants::EMP_MAYA,
            SeederConstants::DEPT_MARKETING => SeederConstants::EMP_DEWI,
            SeederConstants::DEPT_PROJECT   => SeederConstants::EMP_AHMAD,
        ];
        foreach ($managerMap as $deptId => $empId) {
            DB::table('departments')->where('id', $deptId)->update(['manager_id' => $empId]);
        }

        // Seed leave allocations tahun ini
        $this->seedLeaveAllocations($tenantId, $employees, $now);

        $this->command->info('✓ ' . count($employees) . ' employees seeded with salary structures.');
    }

    private function seedLeaveAllocations(string $tenantId, array $employees, $now): void
    {
        $year       = (int) date('Y');
        $leaveTypes = DB::table('leave_types')
            ->where('tenant_id', $tenantId)
            ->pluck('id', 'code');

        if ($leaveTypes->isEmpty()) return;

        $allocMap = [
            'ANNUAL' => 12,
            'SICK'   => 12,
            'UNPAID' => 0,
        ];

        foreach ($employees as $emp) {
            foreach ($allocMap as $code => $days) {
                if (!isset($leaveTypes[$code])) continue;
                DB::table('leave_allocations')->insertOrIgnore([
                    'id'              => (string) Str::uuid(),
                    'tenant_id'       => $tenantId,
                    'employee_id'     => $emp['id'],
                    'leave_type_id'   => $leaveTypes[$code],
                    'year'            => $year,
                    'allocated_days'  => $days,
                    'used_days'       => 0,
                    'pending_days'    => 0,
                    'carry_over_days' => 0,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }
    }
}
