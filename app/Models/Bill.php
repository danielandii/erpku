<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// Bill
// ══════════════════════════════════════════════════════════
class Bill extends Model
{
    use HasUuid, HasTenant, HasAuditLog, HasDocumentNumber, SoftDeletes;

    protected string $documentPrefix       = 'BILL';
    protected string $documentNumberColumn = 'bill_number';

    protected $fillable = [
        'tenant_id', 'bill_number', 'vendor_name', 'vendor_invoice_number',
        'bill_date', 'due_date', 'description', 'coa_id',
        'amount', 'paid_amount', 'status',
        'attachment_url', 'notes', 'approved_by', 'approved_at', 'created_by',
    ];

    protected $casts = [
        'bill_date'   => 'date',
        'due_date'    => 'date',
        'amount'      => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    const STATUS_UNPAID    = 'unpaid';
    const STATUS_PARTIAL   = 'partial';
    const STATUS_PAID      = 'paid';
    const STATUS_OVERDUE   = 'overdue';
    const STATUS_CANCELLED = 'cancelled';

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getRemainingAmountAttribute(): float
    {
        return $this->amount - $this->paid_amount;
    }
}
