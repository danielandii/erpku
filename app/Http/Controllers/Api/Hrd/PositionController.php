<?php
namespace App\Http\Controllers\Api\Hrd;
use App\Http\Controllers\Api\BaseController;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class PositionController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $pos = Position::where('tenant_id',$request->user()->tenant_id)
            ->when($request->department_id,fn($q,$v)=>$q->where('department_id',$v))
            ->orderBy('name')->get();
        return $this->ok($pos);
    }
    public function store(Request $request): JsonResponse
    {
        $v = $request->validate(['name'=>['required','string','max:100'],'level'=>['nullable','string','max:50'],'department_id'=>['nullable','uuid','exists:departments,id']]);
        $pos = Position::create(array_merge($v,['id'=>(string)Str::uuid(),'tenant_id'=>$request->user()->tenant_id,'is_active'=>true]));
        return $this->created($pos,'Jabatan berhasil dibuat.');
    }
    public function show(string $id): JsonResponse { $p=Position::find($id); if(!$p) return $this->notFound('Jabatan'); return $this->ok($p); }
    public function update(Request $request, string $id): JsonResponse
    {
        $p=Position::find($id); if(!$p) return $this->notFound('Jabatan');
        $p->update($request->validate(['name'=>['sometimes','string'],'level'=>['sometimes','nullable','string'],'is_active'=>['sometimes','boolean']]));
        return $this->ok($p,'Jabatan diperbarui.');
    }
    public function destroy(string $id): JsonResponse
    {
        $p=Position::find($id); if(!$p) return $this->notFound('Jabatan');
        if($p->employees()->exists()) return $this->error('Jabatan masih digunakan karyawan.',422,'HAS_EMPLOYEES');
        $p->delete(); return $this->ok(null,'Jabatan dihapus.');
    }
}
