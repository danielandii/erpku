<?php
namespace App\Http\Controllers\Api\Project;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class TaskCommentController extends BaseController
{
    public function index(string $taskId): JsonResponse
    {
        $comments=DB::table('task_comments as tc')
            ->leftJoin('users as u','u.id','=','tc.user_id')
            ->where('tc.task_id',$taskId)->whereNull('tc.deleted_at')
            ->select('tc.*','u.full_name as user_name','u.avatar_url')
            ->orderBy('tc.created_at')->get();
        return $this->ok($comments);
    }
    public function store(Request $request, string $taskId): JsonResponse
    {
        $v=$request->validate(['content'=>['required','string'],'attachment_url'=>['nullable','url']]);
        $id=(string)Str::uuid();
        DB::table('task_comments')->insert(['id'=>$id,'task_id'=>$taskId,'user_id'=>$request->user()->id,'content'=>$v['content'],'attachment_url'=>$v['attachment_url']??null,'created_at'=>now(),'updated_at'=>now()]);
        return $this->created(['id'=>$id],'Komentar berhasil ditambahkan.');
    }
    public function update(Request $request, string $commentId): JsonResponse
    {
        $v=$request->validate(['content'=>['required','string']]);
        DB::table('task_comments')->where('id',$commentId)->where('user_id',$request->user()->id)->update(['content'=>$v['content'],'edited_at'=>now(),'updated_at'=>now()]);
        return $this->ok(['id'=>$commentId],'Komentar diperbarui.');
    }
    public function destroy(string $commentId, Request $request): JsonResponse
    {
        DB::table('task_comments')->where('id',$commentId)->where('user_id',$request->user()->id)->update(['deleted_at'=>now(),'updated_at'=>now()]);
        return $this->ok(null,'Komentar dihapus.');
    }
}
