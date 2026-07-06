<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Api\BaseController;
use App\Models\LeadStage;
use App\Models\QuotationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadStageController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $stages = LeadStage::withCount(['leads' => fn($q) => $q->where('status', 'active')])
            ->where('is_active', true)->orderBy('sequence')->get()
            ->map(fn($s) => ['id'=>$s->id,'name'=>$s->name,'color'=>$s->color,'sequence'=>$s->sequence,'is_won'=>$s->is_won,'is_lost'=>$s->is_lost,'leads_count'=>$s->leads_count]);
        return $this->ok($stages);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $v = $request->validate(['name'=>['required','string','max:100'],'color'=>['nullable','string'],'sequence'=>['nullable','integer','min:0'],'is_won'=>['sometimes','boolean'],'is_lost'=>['sometimes','boolean']]);
        if (!isset($v['sequence'])) $v['sequence'] = (LeadStage::where('tenant_id',$tenantId)->max('sequence') ?? 0) + 10;
        if (($v['is_won']??false) && LeadStage::where('tenant_id',$tenantId)->where('is_won',true)->exists()) return $this->error('Sudah ada stage Won.',422,'DUPLICATE_WON_STAGE');
        if (($v['is_lost']??false) && LeadStage::where('tenant_id',$tenantId)->where('is_lost',true)->exists()) return $this->error('Sudah ada stage Lost.',422,'DUPLICATE_LOST_STAGE');
        $stage = LeadStage::create(array_merge($v,['tenant_id'=>$tenantId,'is_active'=>true]));
        return $this->created(['id'=>$stage->id,'name'=>$stage->name],'Stage pipeline berhasil ditambahkan.');
    }

    public function show(string $id): JsonResponse
    {
        $stage = LeadStage::withCount('leads')->find($id);
        if (!$stage) return $this->notFound('Stage');
        return $this->ok(['id'=>$stage->id,'name'=>$stage->name,'color'=>$stage->color,'sequence'=>$stage->sequence,'is_won'=>$stage->is_won,'is_lost'=>$stage->is_lost,'leads_count'=>$stage->leads_count]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $stage = LeadStage::find($id); if (!$stage) return $this->notFound('Stage');
        $stage->update($request->validate(['name'=>['sometimes','string','max:100'],'color'=>['sometimes','nullable','string'],'sequence'=>['sometimes','integer','min:0'],'is_active'=>['sometimes','boolean']]));
        return $this->ok(['id'=>$stage->id],'Stage berhasil diperbarui.');
    }

    public function destroy(string $id): JsonResponse
    {
        $stage = LeadStage::find($id); if (!$stage) return $this->notFound('Stage');
        if ($stage->is_won || $stage->is_lost) return $this->error('Won/Lost stage tidak dapat dihapus.',422,'CANNOT_DELETE');
        if ($stage->leads()->exists()) return $this->error("Stage masih memiliki leads.",422,'STAGE_HAS_LEADS');
        $stage->delete();
        return $this->ok(null,'Stage berhasil dihapus.');
    }
}
