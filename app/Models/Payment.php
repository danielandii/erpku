<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
