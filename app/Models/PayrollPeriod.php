<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;

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
