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
