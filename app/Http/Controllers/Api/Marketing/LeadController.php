<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Marketing\LeadResource;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/marketing/leads",
     *   tags={"Marketing"},
     *   summary="Daftar leads",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="search",      in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="stage_id",    in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="status",      in="query", @OA\Schema(type="string", enum={"active","on_hold","won","lost"})),
     *   @OA\Parameter(name="assigned_to", in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="source",      in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="overdue_followup", in="query", description="Hanya lead yang follow-up sudah lewat", @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="per_page",    in="query", @OA\Schema(type="integer", default=20)),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/LeadResource")))
     *   )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Lead::with(['stage', 'assignedTo', 'activities' => fn($q) => $q->latest()->limit(1)])
            ->when($request->search, fn($q) =>
                $q->where(fn($q) =>
                    $q->where('contact_name', 'like', "%{$request->search}%")
                      ->orWhere('company_name', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%")
                      ->orWhere('lead_number', 'like', "%{$request->search}%")
                )
            )
            ->when($request->stage_id,    fn($q) => $q->where('stage_id', $request->stage_id))
            ->when($request->status,      fn($q) => $q->where('status', $request->status))
            ->when($request->assigned_to, fn($q) => $q->where('assigned_to', $request->assigned_to))
            ->when($request->source,      fn($q) => $q->where('source', $request->source))
            ->when($request->overdue_followup, fn($q) =>
                $q->where('next_followup_at', '<', now())
                  ->where('status', Lead::STATUS_ACTIVE)
            )
            ->orderByDesc('last_activity_at')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($query, LeadResource::class);
    }

    /**
     * @OA\Post(
     *   path="/marketing/leads",
     *   tags={"Marketing"},
     *   summary="Buat lead baru",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"contact_name","stage_id"},
     *       @OA\Property(property="contact_name",      type="string"),
     *       @OA\Property(property="company_name",      type="string", nullable=true),
     *       @OA\Property(property="email",             type="string", format="email", nullable=true),
     *       @OA\Property(property="phone",             type="string", nullable=true),
     *       @OA\Property(property="stage_id",          type="string", format="uuid"),
     *       @OA\Property(property="source",            type="string"),
     *       @OA\Property(property="assigned_to",       type="string", format="uuid", nullable=true),
     *       @OA\Property(property="expected_revenue",  type="number", nullable=true),
     *       @OA\Property(property="expected_close_date",type="string", format="date", nullable=true),
     *       @OA\Property(property="notes",             type="string", nullable=true),
     *       @OA\Property(property="tags",              type="array", @OA\Items(type="string"))
     *     )
     *   ),
     *   @OA\Response(response=201, description="Lead berhasil dibuat",
     *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/LeadResource"))
     *   )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_name'       => ['required', 'string', 'max:255'],
            'company_name'       => ['nullable', 'string', 'max:255'],
            'email'              => ['nullable', 'email', 'max:255'],
            'phone'              => ['nullable', 'string', 'max:30'],
            'website'            => ['nullable', 'url', 'max:255'],
            'stage_id'           => ['required', 'uuid', 'exists:lead_stages,id'],
            'source'             => ['nullable', 'string', 'max:50'],
            'assigned_to'        => ['nullable', 'uuid', 'exists:users,id'],
            'expected_revenue'   => ['nullable', 'numeric', 'min:0'],
            'probability'        => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date'=> ['nullable', 'date'],
            'notes'              => ['nullable', 'string'],
            'tags'               => ['nullable', 'array'],
            'tags.*'             => ['string', 'max:50'],
        ]);

        $lead = Lead::create(array_merge($validated, [
            'tenant_id'        => $request->user()->tenant_id,
            'status'           => Lead::STATUS_ACTIVE,
            'last_activity_at' => now(),
            'created_by'       => $request->user()->id,
        ]));

        return $this->created(
            new LeadResource($lead->load(['stage', 'assignedTo'])),
            'Lead berhasil dibuat.'
        );
    }

    /**
     * @OA\Get(
     *   path="/marketing/leads/{id}",
     *   tags={"Marketing"},
     *   summary="Detail lead",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $lead = Lead::with(['stage', 'assignedTo', 'activities.user', 'quotations', 'convertedClient', 'createdBy'])->find($id);
        if (! $lead) return $this->notFound('Lead');
        return $this->ok(new LeadResource($lead));
    }

    /**
     * @OA\Patch(
     *   path="/marketing/leads/{id}",
     *   tags={"Marketing"},
     *   summary="Update lead",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (! $lead) return $this->notFound('Lead');

        if ($lead->isWon() || $lead->isLost()) {
            return $this->error('Lead yang sudah Won/Lost tidak dapat diubah.', 422, 'LEAD_CLOSED');
        }

        $validated = $request->validate([
            'contact_name'        => ['sometimes', 'string', 'max:255'],
            'company_name'        => ['sometimes', 'nullable', 'string'],
            'email'               => ['sometimes', 'nullable', 'email'],
            'phone'               => ['sometimes', 'nullable', 'string', 'max:30'],
            'stage_id'            => ['sometimes', 'uuid', 'exists:lead_stages,id'],
            'source'              => ['sometimes', 'nullable', 'string'],
            'assigned_to'         => ['sometimes', 'nullable', 'uuid'],
            'expected_revenue'    => ['sometimes', 'nullable', 'numeric'],
            'probability'         => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['sometimes', 'nullable', 'date'],
            'next_followup_at'    => ['sometimes', 'nullable', 'date'],
            'notes'               => ['sometimes', 'nullable', 'string'],
            'tags'                => ['sometimes', 'nullable', 'array'],
        ]);

        $lead->update(array_merge($validated, ['last_activity_at' => now()]));

        return $this->ok(new LeadResource($lead->load(['stage', 'assignedTo'])), 'Lead berhasil diperbarui.');
    }

    /**
     * @OA\Delete(
     *   path="/marketing/leads/{id}",
     *   tags={"Marketing"},
     *   summary="Hapus lead",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Lead dihapus")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (! $lead) return $this->notFound('Lead');
        $lead->delete();
        return $this->ok(null, 'Lead berhasil dihapus.');
    }

    /** @OA\Patch(path="/marketing/leads/{id}/stage", tags={"Marketing"}, summary="Pindah stage lead", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"stage_id"}, @OA\Property(property="stage_id", type="string", format="uuid"))),
     *   @OA\Response(response=200, description="Stage berhasil diubah")
     * )
     */
    public function changeStage(Request $request, string $id): JsonResponse
    {
        $request->validate(['stage_id' => ['required', 'uuid', 'exists:lead_stages,id']]);
        $lead = Lead::find($id);
        if (! $lead) return $this->notFound('Lead');
        $lead->update(['stage_id' => $request->stage_id, 'last_activity_at' => now()]);
        return $this->ok(new LeadResource($lead->load('stage')), 'Stage berhasil diubah.');
    }

    /** @OA\Patch(path="/marketing/leads/{id}/assign", tags={"Marketing"}, summary="Assign lead ke salesperson", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"assigned_to"}, @OA\Property(property="assigned_to", type="string", format="uuid"))),
     *   @OA\Response(response=200, description="Lead berhasil di-assign")
     * )
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $request->validate(['assigned_to' => ['required', 'uuid', 'exists:users,id']]);
        $lead = Lead::find($id);
        if (! $lead) return $this->notFound('Lead');
        $lead->update(['assigned_to' => $request->assigned_to]);
        return $this->ok(null, 'Lead berhasil di-assign.');
    }

    /**
     * @OA\Post(
     *   path="/marketing/leads/{id}/won",
     *   tags={"Marketing"},
     *   summary="Tandai lead sebagai Won",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Lead ditandai Won")
     * )
     */
    public function markWon(string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (! $lead) return $this->notFound('Lead');
        if ($lead->isWon()) return $this->error('Lead sudah berstatus Won.', 409, 'ALREADY_WON');
        $lead->markAsWon();
        return $this->ok(null, 'Lead berhasil ditandai sebagai Won!');
    }

    /**
     * @OA\Post(
     *   path="/marketing/leads/{id}/lost",
     *   tags={"Marketing"},
     *   summary="Tandai lead sebagai Lost",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(required={"lost_reason"}, @OA\Property(property="lost_reason", type="string"))
     *   ),
     *   @OA\Response(response=200, description="Lead ditandai Lost")
     * )
     */
    public function markLost(Request $request, string $id): JsonResponse
    {
        $request->validate(['lost_reason' => ['required', 'string', 'max:500']]);
        $lead = Lead::find($id);
        if (! $lead) return $this->notFound('Lead');
        if ($lead->isLost()) return $this->error('Lead sudah berstatus Lost.', 409, 'ALREADY_LOST');
        $lead->markAsLost($request->lost_reason);
        return $this->ok(null, 'Lead ditandai sebagai Lost.');
    }

    /**
     * @OA\Post(
     *   path="/marketing/leads/{id}/convert",
     *   tags={"Marketing"},
     *   summary="Konversi lead ke Klien",
     *   description="Membuat record Klien baru dari lead yang Won. Data lead di-copy otomatis.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=201, description="Lead berhasil dikonversi ke Klien")
     * )
     */
    public function convert(Request $request, string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (! $lead) return $this->notFound('Lead');
        if ($lead->isConverted()) return $this->error('Lead sudah pernah dikonversi ke klien.', 409, 'ALREADY_CONVERTED');
        if (! $lead->isWon()) return $this->error('Hanya lead dengan status Won yang dapat dikonversi.', 422, 'LEAD_NOT_WON');

        $client = Client::create([
            'tenant_id'     => $request->user()->tenant_id,
            'type'          => 'company',
            'name'          => $lead->company_name ?? $lead->contact_name,
            'email'         => $lead->email,
            'phone'         => $lead->phone,
            'website'       => $lead->website,
            'assigned_to'   => $lead->assigned_to,
            'source_lead_id'=> $lead->id,
            'notes'         => $lead->notes,
            'tags'          => $lead->tags,
        ]);

        $lead->update(['converted_to_client_id' => $client->id]);

        return $this->created([
            'client_id'     => $client->id,
            'client_number' => $client->client_number,
        ], 'Lead berhasil dikonversi menjadi klien.');
    }

    /** @OA\Get(path="/marketing/leads/{id}/activities", tags={"Marketing"}, summary="Log aktivitas lead", security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function activities(string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (! $lead) return $this->notFound('Lead');
        $acts = $lead->activities()->with('user')->paginate(20);
        return $this->paginated($acts, \App\Http\Resources\Marketing\LeadActivityResource::class);
    }

    /**
     * @OA\Post(
     *   path="/marketing/leads/{id}/activities",
     *   tags={"Marketing"},
     *   summary="Catat aktivitas / last call",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"activity_type","summary","activity_date"},
     *       @OA\Property(property="activity_type",    type="string", enum={"call","email","meeting","note","whatsapp","site_visit","demo"}),
     *       @OA\Property(property="summary",          type="string"),
     *       @OA\Property(property="activity_date",    type="string", format="date-time"),
     *       @OA\Property(property="next_action",      type="string", nullable=true),
     *       @OA\Property(property="next_action_date", type="string", format="date-time", nullable=true),
     *       @OA\Property(property="attachment_url",   type="string", nullable=true)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Aktivitas berhasil dicatat")
     * )
     */
    public function logActivity(Request $request, string $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (! $lead) return $this->notFound('Lead');

        $validated = $request->validate([
            'activity_type'    => ['required', 'in:call,email,meeting,note,whatsapp,site_visit,demo'],
            'summary'          => ['required', 'string', 'max:2000'],
            'activity_date'    => ['required', 'date'],
            'next_action'      => ['nullable', 'string', 'max:500'],
            'next_action_date' => ['nullable', 'date'],
            'attachment_url'   => ['nullable', 'string', 'max:500'],
        ]);

        $activity = LeadActivity::create(array_merge($validated, [
            'tenant_id' => $request->user()->tenant_id,
            'lead_id'   => $lead->id,
            'user_id'   => $request->user()->id,
        ]));

        // Update last_activity_at dan next_followup_at di lead
        $lead->update([
            'last_activity_at' => now(),
            'next_followup_at' => $validated['next_action_date'] ?? $lead->next_followup_at,
        ]);

        return $this->created(
            ['id' => $activity->id, 'activity_type' => $activity->activity_type],
            'Aktivitas berhasil dicatat.'
        );
    }
}
