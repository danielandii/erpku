<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// InvoiceItem
// ══════════════════════════════════════════════════════════
class InvoiceItem extends Model
{
    use HasUuid;

    protected $fillable = [
        'invoice_id', 'sequence', 'description', 'coa_id',
        'quantity', 'unit', 'unit_price', 'discount_percent',
        'tax_percent', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'notes',
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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }
}
