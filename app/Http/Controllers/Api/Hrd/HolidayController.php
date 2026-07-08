<?php
namespace App\Http\Controllers\Api\Hrd;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class HolidayController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        return $this->ok(DB::table('holidays')->where('tenant_id',$request->user()->tenant_id)
            ->when($request->year,fn($q,$v)=>$q->whereYear('holiday_date',$v))
            ->orderBy('holiday_date')->get());
    }
    public function store(Request $request): JsonResponse
    {
        $v=$request->validate(['name'=>['required','string','max:100'],'holiday_date'=>['required','date'],'is_national'=>['sometimes','boolean']]);
        $id=(string)Str::uuid();
        DB::table('holidays')->insert(array_merge($v,['id'=>$id,'tenant_id'=>$request->user()->tenant_id,'created_at'=>now(),'updated_at'=>now()]));
        return $this->created(['id'=>$id],'Hari libur berhasil ditambahkan.');
    }
    public function show(string $id): JsonResponse { $h=DB::table('holidays')->find($id); if(!$h) return $this->notFound('Hari libur'); return $this->ok($h); }
    public function update(Request $request, string $id): JsonResponse
    {
        DB::table('holidays')->where('id',$id)->update(array_merge($request->only(['name','holiday_date','is_national']),['updated_at'=>now()]));
        return $this->ok(['id'=>$id],'Hari libur diperbarui.');
    }
    public function destroy(string $id): JsonResponse { DB::table('holidays')->where('id',$id)->delete(); return $this->ok(null,'Hari libur dihapus.'); }
}
