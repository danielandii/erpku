<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Finance\BankAccountResource;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $accounts = BankAccount::with('coa')
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('is_default')->orderBy('bank_name')->get();
        return $this->ok(BankAccountResource::collection($accounts));
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate(['bank_name'=>['required','string','max:100'],'account_number'=>['required','string','max:50','unique:bank_accounts,account_number'],'account_name'=>['required','string','max:255'],'account_type'=>['required','in:giro,savings,petty_cash'],'coa_id'=>['required','uuid','exists:chart_of_accounts,id'],'branch'=>['nullable','string','max:100'],'currency'=>['sometimes','string','size:3'],'opening_balance'=>['sometimes','numeric','min:0'],'is_default'=>['sometimes','boolean']]);
        if ($v['is_default']??false) BankAccount::where('tenant_id',$request->user()->tenant_id)->update(['is_default'=>false]);
        $a = BankAccount::create(array_merge($v,['tenant_id'=>$request->user()->tenant_id,'current_balance'=>$v['opening_balance']??0,'is_active'=>true]));
        return $this->created(new BankAccountResource($a->load('coa')),'Rekening bank berhasil ditambahkan.');
    }

    public function show(string $id): JsonResponse
    {
        $a = BankAccount::with('coa')->find($id);
        if (!$a) return $this->notFound('Rekening bank');
        return $this->ok(new BankAccountResource($a));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $a = BankAccount::find($id); if (!$a) return $this->notFound('Rekening bank');
        $v = $request->validate(['bank_name'=>['sometimes','string'],'account_name'=>['sometimes','string'],'branch'=>['sometimes','nullable','string'],'is_default'=>['sometimes','boolean'],'is_active'=>['sometimes','boolean']]);
        if ($v['is_default']??false) BankAccount::where('tenant_id',$request->user()->tenant_id)->where('id','!=',$id)->update(['is_default'=>false]);
        $a->update($v);
        return $this->ok(new BankAccountResource($a->load('coa')),'Rekening berhasil diperbarui.');
    }

    public function destroy(string $id): JsonResponse
    {
        $a = BankAccount::find($id); if (!$a) return $this->notFound('Rekening bank');
        if ($a->payments()->exists()) { $a->update(['is_active'=>false]); return $this->ok(null,'Rekening dinonaktifkan.'); }
        $a->delete(); return $this->ok(null,'Rekening berhasil dihapus.');
    }
}
