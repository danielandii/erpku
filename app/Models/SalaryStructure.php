<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;

// ══════════════════════════════════════════════════════════
// SalaryStructure
// ══════════════════════════════════════════════════════════
class SalaryStructure extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'employee_id', 'base_salary',
        'effective_date', 'end_date', 'components', 'notes', 'created_by',
    ];

    protected $casts = [
        'base_salary'    => 'decimal:2',
        'effective_date' => 'date',
        'end_date'       => 'date',
        'components'     => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Ambil total tunjangan dari komponen.
     */
    public function getTotalAllowancesAttribute(): float
    {
        return collect($this->components ?? [])
            ->where('type', 'allowance')
            ->sum('amount');
    }

    /**
     * Ambil total potongan tetap dari komponen.
     */
    public function getTotalFixedDeductionsAttribute(): float
    {
        return collect($this->components ?? [])
            ->where('type', 'deduction')
            ->sum('amount');
    }
}

// ══════════════════════════════════════════════════════════
// PayrollPeriod
// ══════════════════════════════════════════════════════════
class PayrollPeriod extends Model
{
    use HasUuid, HasTenant, HasAuditLog;

    protected $fillable = [
        'tenant_id', 'period_year', 'period_month', 'status',
        'processed_at', 'finalized_at', 'finalized_by',
        'total_gross', 'total_deductions', 'total_net',
        'employee_count', 'notes',
    ];

    protected $casts = [
        'period_year'    => 'integer',
        'period_month'   => 'integer',
        'processed_at'   => 'datetime',
        'finalized_at'   => 'datetime',
        'total_gross'    => 'decimal:2',
        'total_deductions'=> 'decimal:2',
        'total_net'      => 'decimal:2',
    ];

    const STATUS_DRAFT       = 'draft';
    const STATUS_PROCESSING  = 'processing';
    const STATUS_REVIEW      = 'review';
    const STATUS_FINALIZED   = 'finalized';

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function getPeriodLabelAttribute(): string
    {
        $months = [
            1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
            5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
            9=>'September',10=>'Oktober',11=>'November',12=>'Desember',
        ];
        return ($months[$this->period_month] ?? $this->period_month) . ' ' . $this->period_year;
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REVIEW]);
    }
}

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

// ══════════════════════════════════════════════════════════
// EmployeeBonus
// ══════════════════════════════════════════════════════════
class EmployeeBonus extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'employee_id', 'payroll_period_id',
        'bonus_type', 'description', 'amount',
        'is_taxable', 'approved_by',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'is_taxable' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollPeriod()
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
