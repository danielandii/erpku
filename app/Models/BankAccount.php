<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
