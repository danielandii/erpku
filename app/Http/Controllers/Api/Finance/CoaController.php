<?php
namespace App\Http\Controllers\Api\Finance;
use App\Http\Controllers\Api\BaseController;
use App\Models\ChartOfAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class CoaController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $coas = ChartOfAccount::where('tenant_id',$request->user()->tenant_id)
            ->when($request->account_type,fn($q,$v)=>$q->where('account_type',$v))
            ->when($request->is_detail!==null,fn($q)=>$q->where('is_detail',filter_var($request->is_detail,FILTER_VALIDATE_BOOLEAN)))
            ->when($request->is_active!==null,fn($q)=>$q->where('is_active',filter_var($request->is_active,FILTER_VALIDATE_BOOLEAN)))
            ->when($request->search,fn($q,$s)=>$q->where(fn($q)=>$q->where('code','ilike',"%$s%")->orWhere('name','ilike',"%$s%")))
            ->orderBy('code')
            ->paginate($request->per_page??200);
        return $this->paginated($coas);
    }
    public function tree(Request $request): JsonResponse
    {
        $all = ChartOfAccount::where('tenant_id',$request->user()->tenant_id)->where('is_active',true)->orderBy('code')->get();
        $tree = $this->buildTree($all);
        return $this->ok($tree);
    }
    public function store(Request $request): JsonResponse
    {
        $v=$request->validate(['code'=>['required','string','max:20'],'name'=>['required','string','max:255'],'account_type'=>['required','in:asset,liability,equity,revenue,cogs,expense,other_revenue,other_expense'],'normal_balance'=>['required','in:debit,credit'],'level'=>['sometimes','integer','min:1'],'is_detail'=>['sometimes','boolean'],'is_cash_account'=>['sometimes','boolean'],'parent_id'=>['nullable','uuid','exists:chart_of_accounts,id'],'description'=>['nullable','string']]);
        $coa=ChartOfAccount::create(array_merge($v,['id'=>(string)Str::uuid(),'tenant_id'=>$request->user()->tenant_id,'is_active'=>true]));
        return $this->created($coa,'Akun berhasil dibuat.');
    }
    public function show(string $id): JsonResponse
    {
        $coa=ChartOfAccount::with('parent')->find($id);
        if(!$coa) return $this->notFound('Akun');
        return $this->ok($coa);
    }
    public function update(Request $request, string $id): JsonResponse
    {
        $coa=ChartOfAccount::find($id); if(!$coa) return $this->notFound('Akun');
        $coa->update($request->validate(['name'=>['sometimes','string'],'is_detail'=>['sometimes','boolean'],'is_cash_account'=>['sometimes','boolean'],'is_active'=>['sometimes','boolean'],'description'=>['sometimes','nullable','string']]));
        return $this->ok($coa,'Akun diperbarui.');
    }
    public function destroy(string $id): JsonResponse
    {
        $coa=ChartOfAccount::find($id); if(!$coa) return $this->notFound('Akun');
        if(\DB::table('journal_lines')->where('coa_id',$id)->exists()) return $this->error('Akun sudah memiliki transaksi dan tidak dapat dihapus.',422,'HAS_TRANSACTIONS');
        $coa->update(['is_active'=>false]);
        return $this->ok(null,'Akun dinonaktifkan.');
    }
    public function balance(Request $request, string $id): JsonResponse
    {
        $coa=ChartOfAccount::find($id); if(!$coa) return $this->notFound('Akun');
        $asOf=$request->as_of??now()->toDateString();
        $totals=\DB::table('journal_lines as jl')
            ->join('journal_entries as je','je.id','=','jl.journal_entry_id')
            ->where('jl.coa_id',$id)->where('je.is_posted',true)->whereDate('je.entry_date','<=',$asOf)
            ->selectRaw('COALESCE(SUM(debit_amount),0) as total_debit, COALESCE(SUM(credit_amount),0) as total_credit')
            ->first();
        $balance=$coa->normal_balance==='debit'?$totals->total_debit-$totals->total_credit:$totals->total_credit-$totals->total_debit;
        return $this->ok(['coa_id'=>$id,'code'=>$coa->code,'name'=>$coa->name,'as_of'=>$asOf,'total_debit'=>$totals->total_debit,'total_credit'=>$totals->total_credit,'balance'=>$balance,'normal_balance'=>$coa->normal_balance]);
    }
    private function buildTree($items, ?string $parentId=null): array
    {
        $result=[];
        foreach($items->where('parent_id',$parentId) as $item){
            $arr=$item->toArray();
            $children=$this->buildTree($items,$item->id);
            if($children) $arr['children']=$children;
            $result[]=$arr;
        }
        return $result;
    }
}
