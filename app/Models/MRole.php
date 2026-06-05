<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MRole extends Model
{
    use HasFactory, SoftDeletes;

    public function users(){
        return $this->hasMany('\App\Models\User', 'role_id', 'id');
    }
}
