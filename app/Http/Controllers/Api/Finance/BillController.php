<?php
namespace App\Http\Controllers\Api\Finance;
use App\Http\Controllers\Api\BaseController;
use App\Models\Bill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class BillController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $bills=Bill::where('tenant_id',$request->user()->tenant_id)
            ->when($request->status,fn($q,$v)=>$q->where('status',$v))
            ->when($request->search,fn($q,$s)=>$q->where('vendor_name','ilike',"%$s%"))
            ->orderByDesc('bill_date')->paginate($request->per_page??20);
        return $this->paginated($bills);
    }
    public function store(Request $request): JsonResponse
    {
        $v=$request->validate(['vendor_name'=>['required','string','max:255'],'vendor_invoice_number'=>['nullable','string','max:60'],'bill_date'=>['required','date'],'due_date'=>['required','date','after_or_equal:bill_date'],'description'=>['required','string'],'coa_id'=>['nullable','uuid','exists:chart_of_accounts,id'],'amount'=>['required','numeric','min:0'],'notes'=>['nullable','string'],'attachment_url'=>['nullable','url']]);
        $billNumber='BILL-'.date('Y').'-'.str_pad(Bill::where('tenant_id',$request->user()->tenant_id)->count()+1,4,'0',STR_PAD_LEFT);
        $bill=Bill::create(array_merge($v,['id'=>(string)Str::uuid(),'tenant_id'=>$request->user()->tenant_id,'bill_number'=>$billNumber,'paid_amount'=>0,'remaining_amount'=>$v['amount'],'status'=>'unpaid','created_by'=>$request->user()->id]));
        return $this->created($bill,'Tagihan berhasil dibuat.');
    }
    public function show(string $id): JsonResponse { $b=Bill::with('coa')->find($id); if(!$b) return $this->notFound('Tagihan'); return $this->ok($b); }
    public function update(Request $request, string $id): JsonResponse
    {
        $b=Bill::find($id); if(!$b) return $this->notFound('Tagihan');
        if($b->status==='paid') return $this->error('Tagihan yang sudah lunas tidak dapat diedit.',422,'BILL_PAID');
        $b->update($request->validate(['vendor_name'=>['sometimes','string'],'due_date'=>['sometimes','date'],'description'=>['sometimes','string'],'notes'=>['sometimes','nullable','string']]));
        return $this->ok($b,'Tagihan diperbarui.');
    }
    public function approve(string $id, Request $request): JsonResponse
    {
        $b=Bill::find($id); if(!$b) return $this->notFound('Tagihan');
        $b->update(['approved_at'=>now(),'approved_by'=>$request->user()->id]);
        return $this->ok(null,'Tagihan disetujui untuk pembayaran.');
    }
    public function cancel(string $id): JsonResponse
    {
        $b=Bill::find($id); if(!$b) return $this->notFound('Tagihan');
        if($b->paid_amount>0) return $this->error('Tagihan yang sudah dibayar sebagian tidak dapat dibatalkan.',422,'HAS_PAYMENT');
        $b->update(['status'=>'cancelled']);
        return $this->ok(null,'Tagihan dibatalkan.');
    }
}
