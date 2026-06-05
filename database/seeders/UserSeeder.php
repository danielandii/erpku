<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::updateOrCreate([
            'id' => '1',
        ],
        [
            'username' => 'administrator',
            'name' => 'Super Admin',
            'email' => 'admin@nore.web.id',
            'password' => bcrypt('rahasia123'),
            'role_id' => '1',
        ]);
    }
}
