<?php
namespace App\Http\Controllers\Api\Marketing;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class ClientContactController extends BaseController
{
    public function index(string $clientId): JsonResponse
    {
        return $this->ok(DB::table('client_contacts')->where('client_id',$clientId)->orderByDesc('is_primary')->orderBy('name')->get());
    }
    public function store(Request $request, string $clientId): JsonResponse
    {
        $v=$request->validate(['name'=>['required','string','max:255'],'position'=>['nullable','string','max:100'],'email'=>['nullable','email'],'phone'=>['nullable','string','max:30'],'is_primary'=>['sometimes','boolean'],'notes'=>['nullable','string']]);
        if($v['is_primary']??false) DB::table('client_contacts')->where('client_id',$clientId)->update(['is_primary'=>false]);
        $id=(string)Str::uuid();
        DB::table('client_contacts')->insert(array_merge($v,['id'=>$id,'client_id'=>$clientId,'created_at'=>now(),'updated_at'=>now()]));
        return $this->created(['id'=>$id],'Kontak berhasil ditambahkan.');
    }
    public function update(Request $request, string $clientId, string $id): JsonResponse
    {
        $v=$request->validate(['name'=>['sometimes','string'],'position'=>['sometimes','nullable','string'],'email'=>['sometimes','nullable','email'],'phone'=>['sometimes','nullable','string'],'is_primary'=>['sometimes','boolean']]);
        if($v['is_primary']??false) DB::table('client_contacts')->where('client_id',$clientId)->update(['is_primary'=>false]);
        DB::table('client_contacts')->where('id',$id)->update(array_merge($v,['updated_at'=>now()]));
        return $this->ok(['id'=>$id],'Kontak diperbarui.');
    }
    public function destroy(string $clientId, string $id): JsonResponse
    {
        DB::table('client_contacts')->where('id',$id)->where('client_id',$clientId)->delete();
        return $this->ok(null,'Kontak dihapus.');
    }
}
