<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
