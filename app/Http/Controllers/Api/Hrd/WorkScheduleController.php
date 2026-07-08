<?php
namespace App\Http\Controllers\Api\Hrd;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class WorkScheduleController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        return $this->ok(DB::table('work_schedules')->where('tenant_id',$request->user()->tenant_id)->where('is_active',true)->orderBy('name')->get());
    }
    public function store(Request $request): JsonResponse
    {
        $v=$request->validate(['name'=>['required','string'],'work_days'=>['required','array'],'check_in_time'=>['required','date_format:H:i'],'check_out_time'=>['required','date_format:H:i'],'grace_period_minutes'=>['nullable','integer'],'break_duration_minutes'=>['nullable','integer']]);
        $id=(string)Str::uuid();
        DB::table('work_schedules')->insert(array_merge($v,['id'=>$id,'tenant_id'=>$request->user()->tenant_id,'work_days'=>json_encode($v['work_days']),'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]));
        return $this->created(['id'=>$id],'Jadwal kerja berhasil dibuat.');
    }
    public function show(string $id): JsonResponse { $s=DB::table('work_schedules')->find($id); if(!$s) return $this->notFound('Jadwal kerja'); return $this->ok($s); }
    public function update(Request $request, string $id): JsonResponse
    {
        $s=DB::table('work_schedules')->find($id); if(!$s) return $this->notFound('Jadwal kerja');
        DB::table('work_schedules')->where('id',$id)->update(array_merge($request->only(['name','check_in_time','check_out_time','grace_period_minutes','break_duration_minutes','is_active']),['updated_at'=>now()]));
        return $this->ok(['id'=>$id],'Jadwal kerja diperbarui.');
    }
    public function destroy(string $id): JsonResponse { DB::table('work_schedules')->where('id',$id)->update(['is_active'=>false,'updated_at'=>now()]); return $this->ok(null,'Jadwal kerja dinonaktifkan.'); }
}
