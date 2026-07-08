<?php
namespace App\Http\Controllers\Api\Marketing;
use App\Http\Controllers\Api\BaseController;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class ClientController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $clients = Client::with(['assignedTo','contacts'])
            ->where('tenant_id',$request->user()->tenant_id)
            ->when($request->search,fn($q,$s)=>$q->where(fn($q)=>$q->where('name','ilike',"%$s%")->orWhere('email','ilike',"%$s%")))
            ->when($request->type,fn($q,$v)=>$q->where('type',$v))
            ->when($request->is_active!==null,fn($q)=>$q->where('is_active',filter_var($request->is_active,FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('name')
            ->paginate($request->per_page??15);
        return $this->paginated($clients);
    }
    public function store(Request $request): JsonResponse
    {
        $v=$request->validate(['type'=>['required','in:individual,company'],'name'=>['required','string','max:255'],'alias'=>['nullable','string','max:100'],'email'=>['nullable','email'],'phone'=>['nullable','string','max:30'],'address'=>['nullable','string'],'city'=>['nullable','string','max:100'],'province'=>['nullable','string','max:100'],'npwp'=>['nullable','string','max:30'],'website'=>['nullable','url'],'segment'=>['nullable','string','max:50'],'notes'=>['nullable','string']]);
        $c=Client::create(array_merge($v,['id'=>(string)Str::uuid(),'tenant_id'=>$request->user()->tenant_id,'client_number'=>$this->generateClientNumber($request->user()->tenant_id),'tags'=>[],'is_active'=>true,'total_revenue'=>0]));
        return $this->created($c,'Klien berhasil ditambahkan.');
    }
    public function show(string $id): JsonResponse
    {
        $c=Client::with(['contacts','assignedTo'])->find($id);
        if(!$c) return $this->notFound('Klien');
        return $this->ok($c);
    }
    public function update(Request $request, string $id): JsonResponse
    {
        $c=Client::find($id); if(!$c) return $this->notFound('Klien');
        $c->update($request->validate(['name'=>['sometimes','string'],'alias'=>['sometimes','nullable','string'],'email'=>['sometimes','nullable','email'],'phone'=>['sometimes','nullable','string'],'address'=>['sometimes','nullable','string'],'city'=>['sometimes','nullable','string'],'province'=>['sometimes','nullable','string'],'npwp'=>['sometimes','nullable','string'],'website'=>['sometimes','nullable','url'],'segment'=>['sometimes','nullable','string'],'notes'=>['sometimes','nullable','string'],'is_active'=>['sometimes','boolean']]));
        return $this->ok($c,'Klien berhasil diperbarui.');
    }
    public function destroy(string $id): JsonResponse
    {
        $c=Client::find($id); if(!$c) return $this->notFound('Klien');
        $c->delete(); return $this->ok(null,'Klien berhasil dihapus.');
    }
    public function transactions(string $id): JsonResponse
    {
        $invoices=\DB::table('invoices')->where('client_id',$id)->select('invoice_number as number','invoice_date as date','total_amount as amount','status','id')->orderByDesc('invoice_date')->limit(20)->get()->map(fn($i)=>array_merge((array)$i,['type'=>'invoice']));
        $payments=\DB::table('payments')->where('client_id',$id)->select('payment_number as number','payment_date as date','amount','type as status','id')->orderByDesc('payment_date')->limit(10)->get()->map(fn($p)=>array_merge((array)$p,['type'=>'payment']));
        return $this->ok($invoices->merge($payments)->sortByDesc('date')->values());
    }
    private function generateClientNumber(string $tenantId): string
    {
        $count=Client::where('tenant_id',$tenantId)->count()+1;
        return 'CLT-'.date('Y').'-'.str_pad($count,4,'0',STR_PAD_LEFT);
    }
}
