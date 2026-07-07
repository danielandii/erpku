<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadStageSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = SeederConstants::TENANT_ID;
        $now      = now();

        $stages = [
            ['sequence' => 1, 'name' => 'New',         'color' => '#94A3B8', 'is_won' => false, 'is_lost' => false],
            ['sequence' => 2, 'name' => 'Qualified',   'color' => '#3B82F6', 'is_won' => false, 'is_lost' => false],
            ['sequence' => 3, 'name' => 'Proposal',    'color' => '#8B5CF6', 'is_won' => false, 'is_lost' => false],
            ['sequence' => 4, 'name' => 'Negotiation', 'color' => '#F59E0B', 'is_won' => false, 'is_lost' => false],
            ['sequence' => 5, 'name' => 'Won',         'color' => '#22C55E', 'is_won' => true,  'is_lost' => false],
            ['sequence' => 6, 'name' => 'Lost',        'color' => '#EF4444', 'is_won' => false, 'is_lost' => true],
        ];

        foreach ($stages as $stage) {
            DB::table('lead_stages')->insertOrIgnore(array_merge($stage, [
                'id'         => (string) Str::uuid(),
                'tenant_id'  => $tenantId,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->command->info('✓ ' . count($stages) . ' lead stages seeded.');
    }
}