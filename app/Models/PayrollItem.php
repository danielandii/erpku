<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════
// PayrollItem
// ══════════════════════════════════════════════════════════
class PayrollItem extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'payroll_period_id', 'employee_id',
        'base_salary', 'total_allowances', 'total_bonuses',
        'deduction_absent', 'deduction_late', 'deduction_bpjs_kes',
        'deduction_bpjs_tk', 'deduction_pph21', 'deduction_loan',
        'deduction_others', 'gross_salary', 'net_salary',
        'components_detail', 'working_days', 'present_days',
        'absent_days', 'late_count', 'leave_days',
    ];

    protected $casts = [
        'base_salary'       => 'decimal:2',
        'total_allowances'  => 'decimal:2',
        'total_bonuses'     => 'decimal:2',
        'deduction_absent'  => 'decimal:2',
        'deduction_late'    => 'decimal:2',
        'deduction_bpjs_kes'=> 'decimal:2',
        'deduction_bpjs_tk' => 'decimal:2',
        'deduction_pph21'   => 'decimal:2',
        'deduction_loan'    => 'decimal:2',
        'deduction_others'  => 'decimal:2',
        'gross_salary'      => 'decimal:2',
        'net_salary'        => 'decimal:2',
        'components_detail' => 'array',
    ];

    public function payrollPeriod()
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getTotalDeductionsAttribute(): float
    {
        return $this->deduction_absent
            + $this->deduction_late
            + $this->deduction_bpjs_kes
            + $this->deduction_bpjs_tk
            + $this->deduction_pph21
            + $this->deduction_loan
            + $this->deduction_others;
    }
}
