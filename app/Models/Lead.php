<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
