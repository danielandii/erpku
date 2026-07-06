<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Api\BaseController;
use App\Models\QuotationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationTemplateController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/marketing/quotation-templates",
     *   tags={"Marketing"},
     *   summary="Daftar template quotation",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(): JsonResponse
    {
        $templates = QuotationTemplate::where('is_active', true)->orderBy('name')->get()
            ->map(fn($t) => ['id'=>$t->id,'name'=>$t->name,'description'=>$t->description,'items_count'=>count($t->items??[])]);
        return $this->ok($templates);
    }

    /**
     * @OA\Post(
     *   path="/marketing/quotation-templates",
     *   tags={"Marketing"},
     *   summary="Buat template quotation baru",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"name","items"},
     *       @OA\Property(property="name",  type="string"),
     *       @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *     )
     *   ),
     *   @OA\Response(response=201, description="Template berhasil dibuat")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $v = $request->validate(['name'=>['required','string','max:100'],'description'=>['nullable','string'],'terms_conditions'=>['nullable','string'],'items'=>['required','array','min:1'],'items.*.description'=>['required','string'],'items.*.quantity'=>['required','numeric'],'items.*.unit'=>['nullable','string'],'items.*.unit_price'=>['required','numeric']]);
        $t = QuotationTemplate::create(array_merge($v,['tenant_id'=>$request->user()->tenant_id,'is_active'=>true,'created_by'=>$request->user()->id]));
        return $this->created(['id'=>$t->id,'name'=>$t->name],'Template quotation berhasil disimpan.');
    }

    /**
     * @OA\Get(
     *   path="/marketing/quotation-templates/{id}",
     *   tags={"Marketing"},
     *   summary="Detail template",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $t = QuotationTemplate::find($id); if (!$t) return $this->notFound('Template');
        return $this->ok(['id'=>$t->id,'name'=>$t->name,'description'=>$t->description,'terms_conditions'=>$t->terms_conditions,'items'=>$t->items]);
    }

    /**
     * @OA\Patch(
     *   path="/marketing/quotation-templates/{id}",
     *   tags={"Marketing"},
     *   summary="Update template",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $t = QuotationTemplate::find($id); if (!$t) return $this->notFound('Template');
        $t->update($request->validate(['name'=>['sometimes','string'],'description'=>['sometimes','nullable','string'],'terms_conditions'=>['sometimes','nullable','string'],'items'=>['sometimes','array'],'is_active'=>['sometimes','boolean']]));
        return $this->ok(['id'=>$t->id],'Template berhasil diperbarui.');
    }

    /**
     * @OA\Delete(
     *   path="/marketing/quotation-templates/{id}",
     *   tags={"Marketing"},
     *   summary="Hapus template",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $t = QuotationTemplate::find($id); if (!$t) return $this->notFound('Template');
        $t->delete();
        return $this->ok(null,'Template berhasil dihapus.');
    }
}
