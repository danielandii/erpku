<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = SeederConstants::TENANT_ID;
        $now      = now();

        $types = [
            [
                'code'                 => 'ANNUAL',
                'name'                 => 'Cuti Tahunan',
                'default_days'         => 12,
                'is_paid'              => true,
                'carry_over'           => true,
                'max_carry_over_days'  => 6,
                'requires_document'    => false,
                'gender_specific'      => null,
                'max_consecutive_days' => 12,
            ],
            [
                'code'                 => 'SICK',
                'name'                 => 'Cuti Sakit',
                'default_days'         => 12,
                'is_paid'              => true,
                'carry_over'           => false,
                'max_carry_over_days'  => null,
                'requires_document'    => true,
                'gender_specific'      => null,
                'max_consecutive_days' => 3,
            ],
            [
                'code'                 => 'MATERNITY',
                'name'                 => 'Cuti Melahirkan',
                'default_days'         => 90,
                'is_paid'              => true,
                'carry_over'           => false,
                'max_carry_over_days'  => null,
                'requires_document'    => true,
                'gender_specific'      => 'female',
                'max_consecutive_days' => 90,
            ],
            [
                'code'                 => 'PATERNITY',
                'name'                 => 'Cuti Ayah (Kelahiran Anak)',
                'default_days'         => 2,
                'is_paid'              => true,
                'carry_over'           => false,
                'max_carry_over_days'  => null,
                'requires_document'    => true,
                'gender_specific'      => 'male',
                'max_consecutive_days' => 2,
            ],
            [
                'code'                 => 'BEREAVEMENT',
                'name'                 => 'Cuti Duka (Meninggal Keluarga)',
                'default_days'         => 3,
                'is_paid'              => true,
                'carry_over'           => false,
                'max_carry_over_days'  => null,
                'requires_document'    => false,
                'gender_specific'      => null,
                'max_consecutive_days' => 3,
            ],
            [
                'code'                 => 'MARRIAGE',
                'name'                 => 'Cuti Pernikahan',
                'default_days'         => 3,
                'is_paid'              => true,
                'carry_over'           => false,
                'max_carry_over_days'  => null,
                'requires_document'    => true,
                'gender_specific'      => null,
                'max_consecutive_days' => 3,
            ],
            [
                'code'                 => 'UNPAID',
                'name'                 => 'Cuti Tidak Berbayar (CTBB)',
                'default_days'         => 0,
                'is_paid'              => false,
                'carry_over'           => false,
                'max_carry_over_days'  => null,
                'requires_document'    => false,
                'gender_specific'      => null,
                'max_consecutive_days' => null,
            ],
        ];

        foreach ($types as $type) {
            DB::table('leave_types')->insertOrIgnore(array_merge($type, [
                'id'         => (string) Str::uuid(),
                'tenant_id'  => $tenantId,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->command->info('✓ ' . count($types) . ' leave types seeded.');
    }
}