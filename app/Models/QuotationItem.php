<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
