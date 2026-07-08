<?php
namespace App\Http\Controllers\Api\Project;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class TaskAttachmentController extends BaseController
{
    public function index(string $taskId): JsonResponse
    {
        return $this->ok(DB::table('task_attachments')->where('task_id',$taskId)->orderByDesc('created_at')->get());
    }
    public function store(Request $request, string $taskId): JsonResponse
    {
        $request->validate(['file'=>['required','file','max:10240'],'filename'=>['nullable','string']]);
        $file=$request->file('file');
        $path=$file->store("tasks/{$taskId}/attachments",'public');
        $id=(string)Str::uuid();
        DB::table('task_attachments')->insert([
            'id'=>$id,'task_id'=>$taskId,
            'filename'=>$request->filename??$file->getClientOriginalName(),
            'file_url'=>Storage::url($path),
            'mime_type'=>$file->getMimeType(),
            'file_size'=>$file->getSize(),
            'uploaded_by'=>$request->user()->id,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $this->created(['id'=>$id,'file_url'=>Storage::url($path)],'File berhasil diunggah.');
    }
    public function destroy(string $taskId, string $id): JsonResponse
    {
        DB::table('task_attachments')->where('id',$id)->where('task_id',$taskId)->delete();
        return $this->ok(null,'Attachment dihapus.');
    }
}
