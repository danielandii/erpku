<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// Quotation
// ══════════════════════════════════════════════════════════
class Quotation extends Model
{
    use HasUuid, HasTenant, HasAuditLog, HasDocumentNumber, SoftDeletes;

    protected string $documentPrefix       = 'QUO';
    protected string $documentNumberColumn = 'quotation_number';

    protected $fillable = [
        'tenant_id', 'quotation_number', 'version', 'parent_id',
        'lead_id', 'client_id', 'client_name_snapshot',
        'client_address_snapshot', 'date', 'valid_until',
        'pic_user_id', 'currency', 'subtotal',
        'discount_percent', 'discount_amount', 'tax_amount',
        'total_amount', 'terms_conditions', 'notes',
        'status', 'sent_at', 'confirmed_at', 'po_number', 'created_by',
    ];

    protected $casts = [
        'date'             => 'date',
        'valid_until'      => 'date',
        'subtotal'         => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount'  => 'decimal:2',
        'tax_amount'       => 'decimal:2',
        'total_amount'     => 'decimal:2',
        'sent_at'          => 'datetime',
        'confirmed_at'     => 'datetime',
    ];

    const STATUS_DRAFT     = 'draft';
    const STATUS_SENT      = 'sent';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_INVOICED  = 'invoiced';
    const STATUS_EXPIRED   = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    public function items()
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sequence');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function parent()
    {
        return $this->belongsTo(Quotation::class, 'parent_id');
    }

    public function revisions()
    {
        return $this->hasMany(Quotation::class, 'parent_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function pic()
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast()
            && $this->status === self::STATUS_SENT;
    }

    public function canBeInvoiced(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }
}
