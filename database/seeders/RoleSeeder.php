<?php

namespace Database\Seeders;

use App\Models\MRole;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        // DATA MASTER ROLES
        // jika ada yang di delete, comment array dan gunakan id baru
        $datas = array(
            array("id"=>1, "name"=>"Super Admin"),
            array("id"=>2, "name"=>"Manager"),
            array("id"=>3, "name"=>"Admin"),
            array("id"=>4, "name"=>"Koordinator"),
            array("id"=>5, "name"=>"Operator"),
            // array("id"=>6, "name"=>"test"),
        );

        foreach ($datas as $key => $value) {
            MRole::withTrashed()->updateOrCreate([
                'id'    => $value['id'],
            ],
            [
                'name'  => $value['name'],
                'deleted_at'  => null,
            ]);
        }

        //tambahkan id yg dihapus (dicomment)
        $deldata = MRole::whereIn('id', [6,7])->delete();

    }
}
