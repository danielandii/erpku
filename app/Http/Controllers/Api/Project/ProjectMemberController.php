<?php
namespace App\Http\Controllers\Api\Project;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class ProjectMemberController extends BaseController
{
    public function index(string $projectId): JsonResponse
    {
        $members=DB::table('project_members as pm')
            ->join('users as u','u.id','=','pm.user_id')
            ->where('pm.project_id',$projectId)
            ->select('pm.*','u.full_name','u.email','u.avatar_url')
            ->orderBy('pm.role')->orderBy('u.full_name')->get();
        return $this->ok($members);
    }
    public function store(Request $request, string $projectId): JsonResponse
    {
        $v=$request->validate(['user_id'=>['required','uuid','exists:users,id'],'role'=>['sometimes','in:manager,lead,member,observer']]);
        $exists=DB::table('project_members')->where('project_id',$projectId)->where('user_id',$v['user_id'])->exists();
        if($exists) return $this->error('User sudah menjadi anggota proyek ini.',422,'ALREADY_MEMBER');
        $id=(string)Str::uuid();
        DB::table('project_members')->insert(['id'=>$id,'project_id'=>$projectId,'user_id'=>$v['user_id'],'role'=>$v['role']??'member','joined_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return $this->created(['id'=>$id],'Anggota berhasil ditambahkan.');
    }
    public function update(Request $request, string $projectId, string $id): JsonResponse
    {
        $v=$request->validate(['role'=>['required','in:manager,lead,member,observer']]);
        DB::table('project_members')->where('id',$id)->where('project_id',$projectId)->update(array_merge($v,['updated_at'=>now()]));
        return $this->ok(['id'=>$id],'Role anggota diperbarui.');
    }
    public function destroy(string $projectId, string $id): JsonResponse
    {
        DB::table('project_members')->where('id',$id)->where('project_id',$projectId)->delete();
        return $this->ok(null,'Anggota dihapus dari proyek.');
    }
}
