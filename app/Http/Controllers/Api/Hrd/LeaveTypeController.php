<?php
namespace App\Http\Controllers\Api\Hrd;
use App\Http\Controllers\Api\BaseController;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class LeaveTypeController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        return $this->ok(LeaveType::where('tenant_id',$request->user()->tenant_id)->where('is_active',true)->orderBy('name')->get());
    }
    public function store(Request $request): JsonResponse
    {
        $v=$request->validate(['name'=>['required','string','max:100'],'code'=>['required','string','max:20'],'default_days'=>['required','integer','min:0'],'is_paid'=>['required','boolean'],'carry_over'=>['sometimes','boolean'],'max_carry_over_days'=>['nullable','integer'],'requires_document'=>['sometimes','boolean'],'gender_specific'=>['nullable','in:male,female'],'max_consecutive_days'=>['nullable','integer']]);
        $t=LeaveType::create(array_merge($v,['tenant_id'=>$request->user()->tenant_id,'is_active'=>true]));
        return $this->created($t,'Jenis cuti berhasil dibuat.');
    }
    public function show(string $id): JsonResponse { $t=LeaveType::find($id); if(!$t) return $this->notFound('Jenis cuti'); return $this->ok($t); }
    public function update(Request $request, string $id): JsonResponse
    {
        $t=LeaveType::find($id); if(!$t) return $this->notFound('Jenis cuti');
        $t->update($request->validate(['name'=>['sometimes','string'],'default_days'=>['sometimes','integer'],'is_active'=>['sometimes','boolean']]));
        return $this->ok($t,'Jenis cuti diperbarui.');
    }
    public function destroy(string $id): JsonResponse
    {
        $t=LeaveType::find($id); if(!$t) return $this->notFound('Jenis cuti');
        $t->update(['is_active'=>false]); return $this->ok(null,'Jenis cuti dinonaktifkan.');
    }
}
