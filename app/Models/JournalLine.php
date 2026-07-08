<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
