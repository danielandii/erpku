<?php
namespace App\Http\Controllers\Api\Project;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class TaskTimeLogController extends BaseController
{
    public function index(string $taskId): JsonResponse
    {
        $logs=DB::table('task_time_logs as tl')
            ->leftJoin('users as u','u.id','=','tl.user_id')
            ->where('tl.task_id',$taskId)
            ->select('tl.*','u.full_name as user_name')
            ->orderByDesc('tl.log_date')->get();
        return $this->ok($logs);
    }
    public function store(Request $request, string $taskId): JsonResponse
    {
        $v=$request->validate(['log_date'=>['required','date'],'hours'=>['required','numeric','min:0.1','max:24'],'description'=>['nullable','string'],'is_billable'=>['sometimes','boolean']]);
        $id=(string)Str::uuid();
        DB::table('task_time_logs')->insert(array_merge($v,['id'=>$id,'task_id'=>$taskId,'user_id'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]));
        // Update actual hours on task
        $totalHours=DB::table('task_time_logs')->where('task_id',$taskId)->sum('hours');
        DB::table('tasks')->where('id',$taskId)->update(['actual_hours'=>$totalHours,'updated_at'=>now()]);
        return $this->created(['id'=>$id,'total_hours'=>$totalHours],'Waktu berhasil dicatat.');
    }
    public function destroy(string $logId): JsonResponse
    {
        $log=DB::table('task_time_logs')->find($logId);
        if(!$log) return $this->notFound('Time log');
        DB::table('task_time_logs')->where('id',$logId)->delete();
        $totalHours=DB::table('task_time_logs')->where('task_id',$log->task_id)->sum('hours');
        DB::table('tasks')->where('id',$log->task_id)->update(['actual_hours'=>$totalHours,'updated_at'=>now()]);
        return $this->ok(null,'Time log dihapus.');
    }
}
