<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
