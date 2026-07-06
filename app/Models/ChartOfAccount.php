<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// ChartOfAccount
// ══════════════════════════════════════════════════════════
class ChartOfAccount extends Model
{
    use HasUuid, HasTenant;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'tenant_id', 'code', 'name', 'account_type', 'normal_balance',
        'parent_id', 'level', 'is_detail', 'is_cash_account',
        'description', 'is_active',
    ];

    protected $casts = [
        'is_detail'      => 'boolean',
        'is_cash_account'=> 'boolean',
        'is_active'      => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function journalLines()
    {
        return $this->hasMany(JournalLine::class, 'coa_id');
    }

    /**
     * Hitung saldo akun dari journal lines yang sudah di-post.
     */
    public function getBalance(?\Carbon\Carbon $asOf = null): float
    {
        $query = $this->journalLines()
            ->whereHas('journalEntry', fn($q) => $q->where('is_posted', true));

        if ($asOf) {
            $query->whereHas('journalEntry', fn($q) => $q->where('entry_date', '<=', $asOf));
        }

        $debit  = $query->sum('debit_amount');
        $credit = $query->sum('credit_amount');

        return $this->normal_balance === 'debit'
            ? $debit - $credit
            : $credit - $debit;
    }

    public function scopeDetailOnly($query)
    {
        return $query->where('is_detail', true)->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('account_type', $type);
    }
}

// ══════════════════════════════════════════════════════════
// BankAccount
// ══════════════════════════════════════════════════════════
class BankAccount extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'coa_id', 'bank_name', 'account_number',
        'account_name', 'account_type', 'currency',
        'current_balance', 'branch', 'is_active', 'is_default',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'is_active'       => 'boolean',
        'is_default'      => 'boolean',
    ];

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}

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

// ══════════════════════════════════════════════════════════
// Payment
// ══════════════════════════════════════════════════════════
class Payment extends Model
{
    use HasUuid, HasTenant, HasAuditLog, HasDocumentNumber;

    protected string $documentPrefix       = 'PAY';
    protected string $documentNumberColumn = 'payment_number';

    protected $fillable = [
        'tenant_id', 'payment_number', 'type',
        'invoice_id', 'bill_id', 'client_id', 'vendor_name',
        'payment_date', 'amount', 'payment_method',
        'reference_number', 'bank_account_id',
        'notes', 'attachment_url', 'journal_entry_id', 'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    const TYPE_INBOUND  = 'inbound';
    const TYPE_OUTBOUND = 'outbound';

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isInbound(): bool  { return $this->type === self::TYPE_INBOUND; }
    public function isOutbound(): bool { return $this->type === self::TYPE_OUTBOUND; }
}

// ══════════════════════════════════════════════════════════
// JournalEntry
// ══════════════════════════════════════════════════════════
class JournalEntry extends Model
{
    use HasUuid, HasTenant, HasAuditLog, HasDocumentNumber;

    protected string $documentPrefix       = 'JNL';
    protected string $documentNumberColumn = 'entry_number';

    protected $fillable = [
        'tenant_id', 'entry_number', 'type', 'reference_type',
        'reference_id', 'description', 'entry_date',
        'period_year', 'period_month',
        'total_debit', 'total_credit',
        'is_posted', 'posted_at', 'posted_by',
        'is_reversed', 'reversed_by_entry_id', 'created_by',
    ];

    protected $casts = [
        'entry_date'  => 'date',
        'posted_at'   => 'datetime',
        'total_debit' => 'decimal:2',
        'total_credit'=> 'decimal:2',
        'is_posted'   => 'boolean',
        'is_reversed' => 'boolean',
    ];

    public function lines()
    {
        return $this->hasMany(JournalLine::class)->orderBy('sequence');
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedByEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_by_entry_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Validasi bahwa jurnal balance (debit = kredit).
     */
    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.01;
    }

    /**
     * Post jurnal ke General Ledger.
     */
    public function post(User $postedBy): void
    {
        if (! $this->isBalanced()) {
            throw new \Exception("Jurnal tidak balance: Debit={$this->total_debit}, Kredit={$this->total_credit}");
        }

        $this->update([
            'is_posted' => true,
            'posted_at' => now(),
            'posted_by' => $postedBy->id,
        ]);
    }

    /**
     * Buat jurnal reverse (membalikkan entri yang sudah di-post).
     */
    public function reverse(User $createdBy, string $reason = ''): JournalEntry
    {
        if (! $this->is_posted) {
            throw new \Exception('Hanya jurnal yang sudah di-post yang bisa di-reverse.');
        }

        $reversal = static::create([
            'tenant_id'     => $this->tenant_id,
            'type'          => 'adjustment',
            'reference_type'=> 'JournalEntry',
            'reference_id'  => $this->id,
            'description'   => "Reverse: {$this->entry_number}" . ($reason ? " — {$reason}" : ''),
            'entry_date'    => now()->toDateString(),
            'period_year'   => now()->year,
            'period_month'  => now()->month,
            'total_debit'   => $this->total_credit,
            'total_credit'  => $this->total_debit,
            'created_by'    => $createdBy->id,
        ]);

        // Balikkan semua baris (debit ↔ kredit)
        foreach ($this->lines as $line) {
            $reversal->lines()->create([
                'coa_id'        => $line->coa_id,
                'description'   => $line->description,
                'debit_amount'  => $line->credit_amount,
                'credit_amount' => $line->debit_amount,
                'sequence'      => $line->sequence,
            ]);
        }

        $this->update([
            'is_reversed'          => true,
            'reversed_by_entry_id' => $reversal->id,
        ]);

        $reversal->post($createdBy);

        return $reversal;
    }
}

// ══════════════════════════════════════════════════════════
// JournalLine
// ══════════════════════════════════════════════════════════
class JournalLine extends Model
{
    use HasUuid;

    protected $fillable = [
        'journal_entry_id', 'coa_id', 'description',
        'debit_amount', 'credit_amount', 'sequence',
    ];

    protected $casts = [
        'debit_amount'  => 'decimal:2',
        'credit_amount' => 'decimal:2',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }
}
