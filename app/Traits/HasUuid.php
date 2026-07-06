<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Trait HasUuid
 *
 * Otomatis generate UUID v4 saat model dibuat.
 * Gunakan pada semua Eloquent model yang menggunakan UUID sebagai primary key.
 *
 * Usage:
 *   class Invoice extends Model {
 *     use HasUuid;
 *   }
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }
}
