<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use App\Traits\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// ClientContact
// ══════════════════════════════════════════════════════════
class ClientContact extends Model
{
    use HasUuid;

    protected $fillable = [
        'client_id', 'name', 'position', 'email',
        'phone', 'is_primary', 'notes',
    ];

    protected $casts = ['is_primary' => 'boolean'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
