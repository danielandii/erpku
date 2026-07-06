<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// LeadStage
// ══════════════════════════════════════════════════════════
class LeadStage extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'name', 'sequence', 'color',
        'is_won', 'is_lost', 'is_active',
    ];

    protected $casts = [
        'is_won'    => 'boolean',
        'is_lost'   => 'boolean',
        'is_active' => 'boolean',
    ];

    public function leads()
    {
        return $this->hasMany(Lead::class, 'stage_id');
    }
}

// ══════════════════════════════════════════════════════════
// Lead
// ══════════════════════════════════════════════════════════
class Lead extends Model
{
    use HasUuid, HasTenant, HasAuditLog, HasDocumentNumber, SoftDeletes;

    protected string $documentPrefix       = 'LDS';
    protected string $documentNumberColumn = 'lead_number';

    protected $fillable = [
        'tenant_id', 'lead_number', 'contact_name', 'company_name',
        'email', 'phone', 'website', 'stage_id', 'source',
        'assigned_to', 'expected_revenue', 'probability',
        'expected_close_date', 'last_activity_at', 'next_followup_at',
        'score', 'status', 'lost_reason', 'lost_at', 'won_at',
        'converted_to_client_id', 'notes', 'tags', 'created_by',
    ];

    protected $casts = [
        'expected_revenue'    => 'decimal:2',
        'expected_close_date' => 'date',
        'last_activity_at'    => 'datetime',
        'next_followup_at'    => 'datetime',
        'lost_at'             => 'datetime',
        'won_at'              => 'datetime',
        'tags'                => 'array',
    ];

    const STATUS_ACTIVE   = 'active';
    const STATUS_ON_HOLD  = 'on_hold';
    const STATUS_WON      = 'won';
    const STATUS_LOST     = 'lost';

    public function stage()
    {
        return $this->belongsTo(LeadStage::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities()
    {
        return $this->hasMany(LeadActivity::class)->latest('activity_date');
    }

    public function convertedClient()
    {
        return $this->belongsTo(Client::class, 'converted_to_client_id');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tandai lead sebagai Won dan konversi ke klien.
     */
    public function markAsWon(): void
    {
        $wonStage = LeadStage::where('tenant_id', $this->tenant_id)
            ->where('is_won', true)->first();

        $this->update([
            'status'   => self::STATUS_WON,
            'won_at'   => now(),
            'stage_id' => $wonStage?->id ?? $this->stage_id,
        ]);
    }

    /**
     * Tandai lead sebagai Lost dengan alasan.
     */
    public function markAsLost(string $reason): void
    {
        $lostStage = LeadStage::where('tenant_id', $this->tenant_id)
            ->where('is_lost', true)->first();

        $this->update([
            'status'      => self::STATUS_LOST,
            'lost_reason' => $reason,
            'lost_at'     => now(),
            'stage_id'    => $lostStage?->id ?? $this->stage_id,
        ]);
    }

    public function isWon(): bool  { return $this->status === self::STATUS_WON; }
    public function isLost(): bool { return $this->status === self::STATUS_LOST; }
    public function isConverted(): bool { return ! is_null($this->converted_to_client_id); }
}

// ══════════════════════════════════════════════════════════
// LeadActivity
// ══════════════════════════════════════════════════════════
class LeadActivity extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'lead_id', 'user_id', 'activity_type',
        'summary', 'activity_date', 'next_action',
        'next_action_date', 'attachment_url',
    ];

    protected $casts = [
        'activity_date'    => 'datetime',
        'next_action_date' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// ══════════════════════════════════════════════════════════
// Client
// ══════════════════════════════════════════════════════════
class Client extends Model
{
    use HasUuid, HasTenant, HasAuditLog, HasDocumentNumber, SoftDeletes;

    protected string $documentPrefix       = 'CLI';
    protected string $documentNumberColumn = 'client_number';

    protected $fillable = [
        'tenant_id', 'client_number', 'type', 'name', 'alias',
        'email', 'phone', 'address', 'city', 'province',
        'postal_code', 'country', 'npwp', 'website',
        'segment', 'tags', 'assigned_to', 'source_lead_id',
        'notes', 'is_active',
    ];

    protected $casts = [
        'tags'      => 'array',
        'is_active' => 'boolean',
    ];

    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }

    public function primaryContact()
    {
        return $this->hasOne(ClientContact::class)->where('is_primary', true);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function sourceLead()
    {
        return $this->belongsTo(Lead::class, 'source_lead_id');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Total revenue dari semua invoice yang sudah dibayar lunas.
     */
    public function getTotalRevenueAttribute(): float
    {
        return $this->invoices()
            ->where('status', 'paid')
            ->sum('total_amount');
    }
}

// ══════════════════════════════════════════════════════════
// ClientContact
// ══════════════════════════════════════════════════════════
class ClientContact extends Model
{
    use HasUuid;

    protected $fillable = [
        'client_id', 'name', 'position', 'email',
        'phone', 'is_primary', 'notes',
    ];

    protected $casts = ['is_primary' => 'boolean'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

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

// ══════════════════════════════════════════════════════════
// QuotationItem
// ══════════════════════════════════════════════════════════
class QuotationItem extends Model
{
    use HasUuid;

    protected $fillable = [
        'quotation_id', 'sequence', 'description', 'quantity',
        'unit', 'unit_price', 'discount_percent', 'tax_percent',
        'subtotal', 'discount_amount', 'tax_amount', 'total', 'notes',
    ];

    protected $casts = [
        'quantity'         => 'decimal:3',
        'unit_price'       => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'tax_percent'      => 'decimal:2',
        'subtotal'         => 'decimal:2',
        'discount_amount'  => 'decimal:2',
        'tax_amount'       => 'decimal:2',
        'total'            => 'decimal:2',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }
}
