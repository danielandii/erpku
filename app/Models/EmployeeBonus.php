<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;

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
