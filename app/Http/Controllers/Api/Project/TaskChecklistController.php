<?php
namespace App\Http\Controllers\Api\Project;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class TaskChecklistController extends BaseController
{
    public function index(string $taskId): JsonResponse
    {
        return $this->ok(DB::table('task_checklists')->where('task_id',$taskId)->orderBy('sequence')->orderBy('created_at')->get());
    }
    public function store(Request $request, string $taskId): JsonResponse
    {
        $v=$request->validate(['title'=>['required','string','max:255']]);
        $maxSeq=(int)DB::table('task_checklists')->where('task_id',$taskId)->max('sequence');
        $id=(string)Str::uuid();
        DB::table('task_checklists')->insert(['id'=>$id,'task_id'=>$taskId,'title'=>$v['title'],'is_done'=>false,'sequence'=>$maxSeq+1,'created_at'=>now(),'updated_at'=>now()]);
        $this->updateTaskProgress($taskId);
        return $this->created(['id'=>$id],'Checklist ditambahkan.');
    }
    public function toggle(string $checkId, Request $request): JsonResponse
    {
        $item=DB::table('task_checklists')->find($checkId);
        if(!$item) return $this->notFound('Checklist');
        $isDone=!$item->is_done;
        DB::table('task_checklists')->where('id',$checkId)->update(['is_done'=>$isDone,'done_at'=>$isDone?now():null,'done_by'=>$isDone?$request->user()->id:null,'updated_at'=>now()]);
        $this->updateTaskProgress($item->task_id);
        return $this->ok(['id'=>$checkId,'is_done'=>$isDone],'Checklist diperbarui.');
    }
    public function destroy(string $checkId): JsonResponse
    {
        $item=DB::table('task_checklists')->find($checkId);
        if(!$item) return $this->notFound('Checklist');
        DB::table('task_checklists')->where('id',$checkId)->delete();
        $this->updateTaskProgress($item->task_id);
        return $this->ok(null,'Checklist dihapus.');
    }
    private function updateTaskProgress(string $taskId): void
    {
        $total=DB::table('task_checklists')->where('task_id',$taskId)->count();
        $done= DB::table('task_checklists')->where('task_id',$taskId)->where('is_done',true)->count();
        $pct =$total>0?round(($done/$total)*100):0;
        DB::table('tasks')->where('id',$taskId)->update(['progress_percent'=>$pct,'updated_at'=>now()]);
    }
}
