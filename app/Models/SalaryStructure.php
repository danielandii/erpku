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
