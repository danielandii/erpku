<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Trait HasDocumentNumber
 *
 * Auto-generate nomor dokumen berurutan per tenant per tahun.
 * Format dikonfigurasi di tabel settings: {PREFIX}-{YYYY}-{SEQ4}
 *
 * Contoh hasil: INV-2024-0001, QUO-2024-0087, LDS-2024-0012
 *
 * Usage — definisikan $documentPrefix di model:
 *
 *   class Invoice extends Model {
 *     use HasDocumentNumber;
 *     protected string $documentPrefix = 'INV';
 *     protected string $documentNumberColumn = 'invoice_number'; // default: 'number'
 *   }
 */
trait HasDocumentNumber
{
    protected static function bootHasDocumentNumber(): void
    {
        static::creating(function ($model) {
            $column = $model->documentNumberColumn ?? 'number';

            if (empty($model->{$column})) {
                $model->{$column} = static::generateDocumentNumber(
                    $model->tenant_id,
                    $model->documentPrefix
                );
            }
        });
    }

    /**
     * Generate nomor dokumen berikutnya secara atomic (lock untuk cegah race condition).
     */
    public static function generateDocumentNumber(string $tenantId, string $prefix): string
    {
        $year = now()->year;

        return DB::transaction(function () use ($tenantId, $prefix, $year) {
            // Lock row untuk update atomic
            $seq = DB::table('document_sequences')
                ->where('tenant_id', $tenantId)
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                // Buat sequence baru jika belum ada
                DB::table('document_sequences')->insert([
                    'id'            => \Illuminate\Support\Str::uuid(),
                    'tenant_id'     => $tenantId,
                    'prefix'        => $prefix,
                    'year'          => $year,
                    'last_sequence' => 1,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $nextSeq = 1;
            } else {
                $nextSeq = $seq->last_sequence + 1;
                DB::table('document_sequences')
                    ->where('id', $seq->id)
                    ->update([
                        'last_sequence' => $nextSeq,
                        'updated_at'    => now(),
                    ]);
            }

            // Format: INV-2024-0001
            return sprintf('%s-%d-%04d', $prefix, $year, $nextSeq);
        });
    }
}
