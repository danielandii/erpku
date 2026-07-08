<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// Invoice
// ══════════════════════════════════════════════════════════
class Invoice extends Model
{
    use HasUuid, HasTenant, HasAuditLog, HasDocumentNumber, SoftDeletes;

    protected string $documentPrefix       = 'INV';
    protected string $documentNumberColumn = 'invoice_number';

    protected $fillable = [
        'tenant_id', 'invoice_number', 'type', 'parent_invoice_id',
        'quotation_id', 'client_id', 'client_name_snapshot',
        'client_address_snapshot', 'client_npwp_snapshot',
        'invoice_date', 'due_date', 'payment_terms', 'currency',
        'subtotal', 'discount_amount', 'tax_base',
        'ppn_amount', 'pph_amount', 'total_amount',
        'paid_amount', 'remaining_amount', 'status',
        'is_recurring', 'recur_interval', 'recur_next_date', 'recur_end_date',
        'notes', 'internal_notes', 'terms_conditions',
        'attachment_url', 'created_by',
    ];

    protected $casts = [
        'invoice_date'   => 'date',
        'due_date'       => 'date',
        'recur_next_date'=> 'date',
        'recur_end_date' => 'date',
        'subtotal'       => 'decimal:2',
        'discount_amount'=> 'decimal:2',
        'tax_base'       => 'decimal:2',
        'ppn_amount'     => 'decimal:2',
        'pph_amount'     => 'decimal:2',
        'total_amount'   => 'decimal:2',
        'paid_amount'    => 'decimal:2',
        'remaining_amount'=> 'decimal:2',
        'is_recurring'   => 'boolean',
    ];

    const STATUS_DRAFT        = 'draft';
    const STATUS_OPEN         = 'open';
    const STATUS_PARTIAL_PAID = 'partial_paid';
    const STATUS_PAID         = 'paid';
    const STATUS_OVERDUE      = 'overdue';
    const STATUS_CANCELLED    = 'cancelled';
    const STATUS_VOID         = 'void';

    // ── Relationships ──────────────────────────────────────

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sequence');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function parentInvoice()
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    public function creditNotes()
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id')
            ->where('type', 'credit_note');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Business Logic ─────────────────────────────────────

    public function isOverdue(): bool
    {
        return $this->due_date->isPast()
            && ! in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED, self::STATUS_VOID]);
    }

    public function isFullyPaid(): bool
    {
        return $this->remaining_amount <= 0;
    }

    /**
     * Recalculate remaining_amount dan update status.
     * Dipanggil setiap kali ada pembayaran baru.
     */
    public function recalculateStatus(): void
    {
        $remaining = $this->total_amount - $this->paid_amount;
        $status    = $this->status;

        if ($remaining <= 0) {
            $status    = self::STATUS_PAID;
            $remaining = 0;
        } elseif ($this->paid_amount > 0) {
            $status = self::STATUS_PARTIAL_PAID;
        } elseif ($this->due_date->isPast()) {
            $status = self::STATUS_OVERDUE;
        }

        $this->update([
            'remaining_amount' => $remaining,
            'status'           => $status,
        ]);
    }
}
