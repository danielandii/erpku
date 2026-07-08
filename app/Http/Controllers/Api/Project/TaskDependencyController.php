<?php
namespace App\Http\Controllers\Api\Project;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class TaskDependencyController extends BaseController
{
    public function store(Request $request, string $taskId): JsonResponse
    {
        $v=$request->validate(['depends_on_task_id'=>['required','uuid','exists:tasks,id','different:task_id'],'dependency_type'=>['sometimes','in:FS,SS,FF,SF']]);
        if($this->hasCircular($taskId,$v['depends_on_task_id'])) return $this->error('Circular dependency terdeteksi.',422,'CIRCULAR_DEPENDENCY');
        $exists=DB::table('task_dependencies')->where('task_id',$taskId)->where('depends_on_task_id',$v['depends_on_task_id'])->exists();
        if($exists) return $this->error('Dependensi sudah ada.',422,'DEPENDENCY_EXISTS');
        $id=(string)Str::uuid();
        DB::table('task_dependencies')->insert(['id'=>$id,'task_id'=>$taskId,'depends_on_task_id'=>$v['depends_on_task_id'],'dependency_type'=>$v['dependency_type']??'FS','created_at'=>now(),'updated_at'=>now()]);
        return $this->created(['id'=>$id],'Dependensi berhasil ditambahkan.');
    }
    public function destroy(string $taskId, string $depId): JsonResponse
    {
        DB::table('task_dependencies')->where('id',$depId)->where('task_id',$taskId)->delete();
        return $this->ok(null,'Dependensi dihapus.');
    }
    private function hasCircular(string $taskId, string $depId, array $visited=[]): bool
    {
        if($depId===$taskId) return true;
        if(in_array($depId,$visited)) return false;
        $visited[]=$depId;
        $upstream=DB::table('task_dependencies')->where('task_id',$depId)->pluck('depends_on_task_id');
        foreach($upstream as $u){ if($this->hasCircular($taskId,$u,$visited)) return true; }
        return false;
    }
}
