<?php
namespace App\Http\Controllers\Api\Hrd;
use App\Http\Controllers\Api\BaseController;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class DepartmentController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $depts = Department::withCount('employees')->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')->get();
        return $this->ok($depts);
    }
    public function store(Request $request): JsonResponse
    {
        $v = $request->validate(['name'=>['required','string','max:100'],'code'=>['nullable','string','max:20'],'parent_id'=>['nullable','uuid']]);
        $dept = Department::create(array_merge($v,['id'=>(string)Str::uuid(),'tenant_id'=>$request->user()->tenant_id,'is_active'=>true]));
        return $this->created($dept,'Department berhasil dibuat.');
    }
    public function show(string $id): JsonResponse
    {
        $d = Department::withCount('employees')->find($id);
        if(!$d) return $this->notFound('Department');
        return $this->ok($d);
    }
    public function update(Request $request, string $id): JsonResponse
    {
        $d = Department::find($id); if(!$d) return $this->notFound('Department');
        $d->update($request->validate(['name'=>['sometimes','string'],'code'=>['sometimes','nullable','string'],'manager_id'=>['sometimes','nullable','uuid'],'is_active'=>['sometimes','boolean']]));
        return $this->ok($d,'Department diperbarui.');
    }
    public function destroy(string $id): JsonResponse
    {
        $d = Department::find($id); if(!$d) return $this->notFound('Department');
        if($d->employees()->exists()) return $this->error('Department masih memiliki karyawan.',422,'HAS_EMPLOYEES');
        $d->delete(); return $this->ok(null,'Department dihapus.');
    }
}
