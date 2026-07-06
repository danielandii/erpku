<?php

namespace App\Services\Hrd;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    /**
     * Proses kalkulasi gaji semua karyawan aktif untuk satu periode.
     */
    public function processPeriod(PayrollPeriod $period): array
    {
        $tenantId = $period->tenant_id;

        $period->update(['status' => PayrollPeriod::STATUS_PROCESSING]);

        // Hapus item lama jika reprocess
        PayrollItem::where('payroll_period_id', $period->id)->delete();

        $employees = Employee::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['salaryStructure', 'workSchedule'])
            ->get();

        $items = [];

        foreach ($employees as $employee) {
            $item = $this->calculateEmployeePayroll($employee, $period);
            $items[] = $item;
        }

        $totalGross      = collect($items)->sum('gross_salary');
        $totalDeductions = collect($items)->sum(fn($i) =>
            $i['deduction_absent'] + $i['deduction_late'] + $i['deduction_bpjs_kes'] +
            $i['deduction_bpjs_tk'] + $i['deduction_pph21'] + $i['deduction_loan'] + $i['deduction_others']
        );
        $totalNet = collect($items)->sum('net_salary');

        $period->update([
            'status'          => PayrollPeriod::STATUS_REVIEW,
            'processed_at'    => now(),
            'total_gross'     => $totalGross,
            'total_deductions'=> $totalDeductions,
            'total_net'       => $totalNet,
            'employee_count'  => count($items),
        ]);

        return [
            'processed_count' => count($items),
            'total_gross'     => $totalGross,
            'total_deductions'=> $totalDeductions,
            'total_net'       => $totalNet,
        ];
    }

    /**
     * Kalkulasi gaji satu karyawan untuk satu periode.
     */
    private function calculateEmployeePayroll(Employee $employee, PayrollPeriod $period): array
    {
        $year  = $period->period_year;
        $month = $period->period_month;

        $salaryStructure = $employee->salaryStructure;
        $baseSalary      = $salaryStructure ? (float) $salaryStructure->base_salary : 0;
        $components      = $salaryStructure?->components ?? [];

        // ── Hitung hari kerja bulan ini ───────────────────────
        $workdayStats = $this->getWorkdayStats($employee->id, $year, $month);

        // ── Tunjangan & Potongan Tetap ────────────────────────
        $totalAllowances      = 0;
        $fixedDeductions      = 0;
        $allowanceDetails     = [];
        $deductionDetails     = [];

        foreach ($components as $comp) {
            if ($comp['type'] === 'allowance') {
                $totalAllowances      += (float) $comp['amount'];
                $allowanceDetails[]   = $comp;
            } elseif ($comp['type'] === 'deduction') {
                $fixedDeductions      += (float) $comp['amount'];
                $deductionDetails[]   = $comp;
            }
        }

        // ── Bonus Bulan Ini ───────────────────────────────────
        $bonuses = EmployeeBonus::where('employee_id', $employee->id)
            ->where('payroll_period_id', $period->id)
            ->get();

        $totalBonuses  = $bonuses->sum('amount');
        $bonusDetails  = $bonuses->map(fn($b) => [
            'type'        => $b->bonus_type,
            'description' => $b->description,
            'amount'      => (float) $b->amount,
            'is_taxable'  => $b->is_taxable,
        ])->toArray();

        // ── Potongan Ketidakhadiran ───────────────────────────
        $deductionPerDay = $workdayStats['working_days'] > 0
            ? $baseSalary / $workdayStats['working_days']
            : 0;

        $deductionAbsent = round($deductionPerDay * $workdayStats['absent_days'], 2);

        // Potongan keterlambatan (per menit)
        $lateDeductionPerMinute = (float) Setting::getValue(
            $employee->tenant_id, 'hrd.late_deduction_per_minute', 0
        );
        $deductionLate = round($workdayStats['total_late_minutes'] * $lateDeductionPerMinute, 2);

        // ── BPJS ─────────────────────────────────────────────
        $bpjsKesRate = (float) Setting::getValue($employee->tenant_id, 'hrd.bpjs_kes_employee_rate', 1);
        $bpjsTkRate  = (float) Setting::getValue($employee->tenant_id, 'hrd.bpjs_tk_jht_employee_rate', 2);
        $bpjsJpRate  = (float) Setting::getValue($employee->tenant_id, 'hrd.bpjs_tk_jp_employee_rate', 1);

        $bpjsBase       = min($baseSalary, 12000000); // Batas upah untuk BPJS Kes
        $deductionBpjsKes = round($bpjsBase * ($bpjsKesRate / 100), 2);
        $deductionBpjsTk  = round($baseSalary * (($bpjsTkRate + $bpjsJpRate) / 100), 2);

        // ── Gross Salary ──────────────────────────────────────
        $grossSalary = $baseSalary + $totalAllowances + $totalBonuses;

        // ── PPh 21 ────────────────────────────────────────────
        $deductionPph21 = $this->calculatePph21(
            $employee,
            $grossSalary,
            $deductionBpjsKes + $deductionBpjsTk,
            $year
        );

        // ── Total Potongan & Net ──────────────────────────────
        $totalDeductions = $deductionAbsent + $deductionLate
            + $deductionBpjsKes + $deductionBpjsTk
            + $deductionPph21 + $fixedDeductions;

        $netSalary = max(0, $grossSalary - $totalDeductions);

        $itemData = [
            'tenant_id'          => $employee->tenant_id,
            'payroll_period_id'  => $period->id,
            'employee_id'        => $employee->id,
            'base_salary'        => $baseSalary,
            'total_allowances'   => $totalAllowances,
            'total_bonuses'      => $totalBonuses,
            'deduction_absent'   => $deductionAbsent,
            'deduction_late'     => $deductionLate,
            'deduction_bpjs_kes' => $deductionBpjsKes,
            'deduction_bpjs_tk'  => $deductionBpjsTk,
            'deduction_pph21'    => $deductionPph21,
            'deduction_loan'     => $fixedDeductions,
            'deduction_others'   => 0,
            'gross_salary'       => $grossSalary,
            'net_salary'         => $netSalary,
            'working_days'       => $workdayStats['working_days'],
            'present_days'       => $workdayStats['present_days'],
            'absent_days'        => $workdayStats['absent_days'],
            'late_count'         => $workdayStats['late_count'],
            'leave_days'         => $workdayStats['leave_days'],
            'components_detail'  => json_encode([
                'allowances'    => $allowanceDetails,
                'bonuses'       => $bonusDetails,
                'fixed_deductions' => $deductionDetails,
                'pph21_detail'  => ['annual_gross' => $grossSalary * 12, 'monthly_tax' => $deductionPph21],
            ]),
        ];

        PayrollItem::create($itemData);

        return $itemData;
    }

    /**
     * Ambil statistik kehadiran karyawan untuk bulan tertentu.
     */
    private function getWorkdayStats(string $employeeId, int $year, int $month): array
    {
        $records = Attendance::where('employee_id', $employeeId)
            ->whereYear('attendance_date', $year)
            ->whereMonth('attendance_date', $month)
            ->get();

        $workingDays   = $records->whereNotIn('status', ['holiday'])->count();
        $presentDays   = $records->whereIn('status', ['present', 'late'])->count();
        $absentDays    = $records->where('status', 'absent')->count();
        $lateDays      = $records->where('status', 'late')->count();
        $leaveDays     = $records->whereIn('status', ['leave', 'sick', 'permission'])->count();
        $totalLateMin  = $records->where('status', 'late')->sum('late_minutes');

        return [
            'working_days'       => $workingDays,
            'present_days'       => $presentDays,
            'absent_days'        => $absentDays,
            'late_count'         => $lateDays,
            'leave_days'         => $leaveDays,
            'total_late_minutes' => $totalLateMin,
        ];
    }

    /**
     * Kalkulasi PPh 21 bulanan (metode sederhana gross-up).
     * Referensi: UU HPP No. 7/2021, tarif progresif.
     */
    private function calculatePph21(Employee $emp, float $grossMonthly, float $bpjsDeduction, int $year): float
    {
        // PTKP 2024 (Penghasilan Tidak Kena Pajak)
        $ptkp = match ($emp->num_dependants ?? 'TK0') {
            'TK0'  => 54_000_000,
            'TK1'  => 58_500_000,
            'TK2'  => 63_000_000,
            'TK3'  => 67_500_000,
            'K0'   => 58_500_000,
            'K1'   => 63_000_000,
            'K2'   => 67_500_000,
            'K3'   => 72_000_000,
            default=> 54_000_000,
        };

        $annualGross       = $grossMonthly * 12;
        $jabatanDeduction  = min($annualGross * 0.05, 6_000_000); // Max Rp 6jt/tahun
        $bpjsAnnual        = $bpjsDeduction * 12;
        $pkp               = max(0, $annualGross - $jabatanDeduction - $bpjsAnnual - $ptkp);

        // Tarif progresif PPh 21 (Pasal 17)
        $annualTax = 0;
        if ($pkp <= 60_000_000) {
            $annualTax = $pkp * 0.05;
        } elseif ($pkp <= 250_000_000) {
            $annualTax = 3_000_000 + ($pkp - 60_000_000) * 0.15;
        } elseif ($pkp <= 500_000_000) {
            $annualTax = 33_000_000 + ($pkp - 250_000_000) * 0.25;
        } elseif ($pkp <= 5_000_000_000) {
            $annualTax = 95_500_000 + ($pkp - 500_000_000) * 0.30;
        } else {
            $annualTax = 1_445_500_000 + ($pkp - 5_000_000_000) * 0.35;
        }

        return round($annualTax / 12, 2);
    }

    /**
     * Finalisasi periode payroll dan buat jurnal Finance.
     */
    public function finalizePeriod(PayrollPeriod $period, User $user): void
    {
        DB::transaction(function () use ($period, $user) {
            $period->update([
                'status'       => PayrollPeriod::STATUS_FINALIZED,
                'finalized_at' => now(),
                'finalized_by' => $user->id,
            ]);

            // Buat jurnal beban gaji di modul Finance (jika modul Finance aktif)
            $this->createPayrollJournal($period, $user);
        });
    }

    /**
     * Buat jurnal double-entry untuk beban gaji.
     * Dr. Beban Gaji (6101)   = total_gross
     * Dr. Beban BPJS Employer = total_employer_bpjs
     * Cr. Hutang Gaji  (2404) = total_net
     * Cr. Hutang PPh21 (2201) = total_pph21
     * Cr. Hutang BPJS  (2300) = total_bpjs_employee + employer
     */
    private function createPayrollJournal(PayrollPeriod $period, User $user): void
    {
        $items     = PayrollItem::where('payroll_period_id', $period->id)->get();
        $tenantId  = $period->tenant_id;

        $totalGross   = $items->sum('gross_salary');
        $totalNet     = $items->sum('net_salary');
        $totalPph21   = $items->sum('deduction_pph21');
        $totalBpjsKes = $items->sum('deduction_bpjs_kes');
        $totalBpjsTk  = $items->sum('deduction_bpjs_tk');
        $totalBpjs    = $totalBpjsKes + $totalBpjsTk;

        // Ambil COA IDs dari settings
        $coaSalaryExp = $this->getCoaId($tenantId, 'finance.coa_salary_expense', '6101');
        $coaHutangGaji= $this->getCoaId($tenantId, 'finance.coa_hutang_gaji', '2404');
        $coaHutangPph = $this->getCoaId($tenantId, null, '2201');
        $coaHutangBpjs= $this->getCoaId($tenantId, null, '2300');

        if (! $coaSalaryExp || ! $coaHutangGaji) return; // COA belum dikonfigurasi

        $journal = \App\Models\JournalEntry::create([
            'tenant_id'      => $tenantId,
            'type'           => 'payroll',
            'reference_type' => 'PayrollPeriod',
            'reference_id'   => $period->id,
            'description'    => "Beban Gaji {$period->period_label}",
            'entry_date'     => now()->toDateString(),
            'period_year'    => $period->period_year,
            'period_month'   => $period->period_month,
            'total_debit'    => $totalGross,
            'total_credit'   => $totalGross,
            'created_by'     => $user->id,
        ]);

        // Debit: Beban Gaji
        $journal->lines()->create([
            'coa_id'       => $coaSalaryExp,
            'description'  => "Beban Gaji Karyawan {$period->period_label}",
            'debit_amount' => $totalGross,
            'credit_amount'=> 0,
            'sequence'     => 1,
        ]);

        // Kredit: Hutang Gaji (net)
        $journal->lines()->create([
            'coa_id'       => $coaHutangGaji,
            'description'  => "Hutang Gaji Karyawan",
            'debit_amount' => 0,
            'credit_amount'=> $totalNet,
            'sequence'     => 2,
        ]);

        // Kredit: Hutang PPh 21
        if ($totalPph21 > 0 && $coaHutangPph) {
            $journal->lines()->create([
                'coa_id'       => $coaHutangPph,
                'description'  => "Hutang PPh 21 Karyawan",
                'debit_amount' => 0,
                'credit_amount'=> $totalPph21,
                'sequence'     => 3,
            ]);
        }

        // Kredit: Hutang BPJS
        if ($totalBpjs > 0 && $coaHutangBpjs) {
            $journal->lines()->create([
                'coa_id'       => $coaHutangBpjs,
                'description'  => "Hutang BPJS Karyawan",
                'debit_amount' => 0,
                'credit_amount'=> $totalBpjs,
                'sequence'     => 4,
            ]);
        }

        $journal->post($user);
    }

    private function getCoaId(string $tenantId, ?string $settingKey, string $defaultCode): ?string
    {
        $code = $settingKey
            ? Setting::getValue($tenantId, $settingKey, $defaultCode)
            : $defaultCode;

        return \App\Models\ChartOfAccount::where('tenant_id', $tenantId)
            ->where('code', $code)
            ->value('id');
    }
}
