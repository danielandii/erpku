<?php
namespace App\Http\Controllers\Api\Hrd;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class LeaveAllocationController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $allocs = DB::table('leave_allocations as la')
            ->join('employees as e','e.id','=','la.employee_id')
            ->join('leave_types as lt','lt.id','=','la.leave_type_id')
            ->where('la.tenant_id',$request->user()->tenant_id)
            ->when($request->year,fn($q,$v)=>$q->where('la.year',$v))
            ->when($request->employee_id,fn($q,$v)=>$q->where('la.employee_id',$v))
            ->select('la.*','e.full_name as employee_name','e.employee_number','lt.name as leave_type_name','lt.code as leave_type_code')
            ->orderBy('e.full_name')
            ->paginate($request->per_page??20);
        return $this->paginated($allocs);
    }
    public function store(Request $request): JsonResponse
    {
        $v=$request->validate(['employee_id'=>['required','uuid','exists:employees,id'],'leave_type_id'=>['required','uuid','exists:leave_types,id'],'year'=>['required','integer','min:2020'],'allocated_days'=>['required','integer','min:0'],'carry_over_days'=>['sometimes','integer','min:0']]);
        $id=(string)Str::uuid();
        DB::table('leave_allocations')->insertOrIgnore(array_merge($v,['id'=>$id,'tenant_id'=>$request->user()->tenant_id,'used_days'=>0,'pending_days'=>0,'created_at'=>now(),'updated_at'=>now()]));
        return $this->created(['id'=>$id],'Alokasi cuti berhasil disimpan.');
    }
    public function bulkAllocate(Request $request): JsonResponse
    {
        $v=$request->validate(['year'=>['required','integer'],'leave_type_id'=>['required','uuid'],'allocated_days'=>['required','integer','min:0'],'employee_ids'=>['required','array'],'employee_ids.*'=>['uuid']]);
        $count=0;
        foreach($v['employee_ids'] as $empId){
            $exists=DB::table('leave_allocations')->where('employee_id',$empId)->where('leave_type_id',$v['leave_type_id'])->where('year',$v['year'])->exists();
            if(!$exists){
                DB::table('leave_allocations')->insert(['id'=>(string)Str::uuid(),'tenant_id'=>$request->user()->tenant_id,'employee_id'=>$empId,'leave_type_id'=>$v['leave_type_id'],'year'=>$v['year'],'allocated_days'=>$v['allocated_days'],'used_days'=>0,'pending_days'=>0,'carry_over_days'=>0,'created_at'=>now(),'updated_at'=>now()]);
                $count++;
            }
        }
        return $this->ok(['created'=>$count],"$count alokasi cuti berhasil dibuat.");
    }
}
