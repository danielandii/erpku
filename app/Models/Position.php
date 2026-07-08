<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\HasTenant;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ══════════════════════════════════════════════════════════
// Position
// ══════════════════════════════════════════════════════════
class Position extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id', 'department_id', 'name', 'level', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
