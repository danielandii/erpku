<?php

namespace App\Http\Controllers\Api\Hrd;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Hrd\LeaveRequestResource;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/hrd/leave-requests",
     *   tags={"HRD"},
     *   summary="Daftar pengajuan cuti",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="employee_id",   in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="status",        in="query", @OA\Schema(type="string", enum={"draft","pending","approved","rejected","cancelled"})),
     *   @OA\Parameter(name="leave_type_id", in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="date_from",     in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="date_to",       in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="per_page",      in="query", @OA\Schema(type="integer", default=15)),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/LeaveRequestResource")))
     *   )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $isAdmin  = $user->hasAnyPermission(['hrd.leave.approve', 'hrd.leave.manage']);

        $query = LeaveRequest::with(['employee', 'leaveType', 'approverL1', 'approverL2'])
            ->when(! $isAdmin, function ($q) use ($user) {
                // Non-admin hanya lihat milik sendiri
                $q->whereHas('employee', fn($q) => $q->where('user_id', $user->id));
            })
            ->when($request->employee_id,   fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->status,        fn($q) => $q->where('status', $request->status))
            ->when($request->leave_type_id, fn($q) => $q->where('leave_type_id', $request->leave_type_id))
            ->when($request->date_from,     fn($q) => $q->where('start_date', '>=', $request->date_from))
            ->when($request->date_to,       fn($q) => $q->where('end_date',   '<=', $request->date_to))
            ->latest();

        return $this->paginated(
            $query->paginate($request->per_page ?? 15),
            LeaveRequestResource::class
        );
    }

    /**
     * @OA\Post(
     *   path="/hrd/leave-requests",
     *   tags={"HRD"},
     *   summary="Ajukan cuti baru",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"leave_type_id","start_date","end_date","reason"},
     *       @OA\Property(property="leave_type_id", type="string", format="uuid"),
     *       @OA\Property(property="start_date",    type="string", format="date", example="2024-06-20"),
     *       @OA\Property(property="end_date",      type="string", format="date", example="2024-06-21"),
     *       @OA\Property(property="reason",        type="string"),
     *       @OA\Property(property="document_url",  type="string", nullable=true),
     *       @OA\Property(property="employee_id",   type="string", format="uuid", description="Opsional: jika HR mengajukan untuk karyawan lain")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Pengajuan cuti berhasil dibuat",
     *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/LeaveRequestResource"))
     *   ),
     *   @OA\Response(response=422, description="Validasi gagal / saldo cuti tidak cukup")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leave_type_id' => ['required', 'uuid', 'exists:leave_types,id'],
            'start_date'    => ['required', 'date', 'after_or_equal:today'],
            'end_date'      => ['required', 'date', 'after_or_equal:start_date'],
            'reason'        => ['required', 'string', 'max:1000'],
            'document_url'  => ['nullable', 'string', 'max:500'],
            'employee_id'   => ['nullable', 'uuid', 'exists:employees,id'],
        ]);

        // Resolve employee
        $employee = $validated['employee_id']
            ? Employee::find($validated['employee_id'])
            : Employee::where('user_id', $request->user()->id)->first();

        if (! $employee) {
            return $this->error('Data karyawan tidak ditemukan.', 404, 'EMPLOYEE_NOT_FOUND');
        }

        // Hitung hari kerja (exclude weekend & hari libur)
        $workdays = $this->countWorkdays(
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date']),
            $request->user()->tenant_id,
            $employee->workSchedule
        );

        if ($workdays <= 0) {
            return $this->error('Tanggal yang dipilih tidak mencakup hari kerja.', 422, 'NO_WORKDAYS');
        }

        // Cek saldo cuti
        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $validated['leave_type_id'])
            ->where('year', Carbon::parse($validated['start_date'])->year)
            ->first();

        if ($allocation) {
            $remaining = $allocation->remaining_days;
            if ($workdays > $remaining) {
                return $this->error(
                    "Saldo cuti tidak mencukupi. Sisa: {$remaining} hari, diperlukan: {$workdays} hari.",
                    422, 'INSUFFICIENT_LEAVE_BALANCE'
                );
            }

            // Reserve pending days
            $allocation->increment('pending_days', $workdays);
        }

        $leaveRequest = LeaveRequest::create([
            'tenant_id'     => $request->user()->tenant_id,
            'employee_id'   => $employee->id,
            'leave_type_id' => $validated['leave_type_id'],
            'start_date'    => $validated['start_date'],
            'end_date'      => $validated['end_date'],
            'total_days'    => $workdays,
            'reason'        => $validated['reason'],
            'document_url'  => $validated['document_url'] ?? null,
            'status'        => LeaveRequest::STATUS_PENDING,
        ]);

        return $this->created(
            new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType'])),
            "Pengajuan cuti {$workdays} hari berhasil diajukan."
        );
    }

    /**
     * @OA\Get(
     *   path="/hrd/leave-requests/{id}",
     *   tags={"HRD"},
     *   summary="Detail pengajuan cuti",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $leave = LeaveRequest::with(['employee', 'leaveType', 'approverL1', 'approverL2'])->find($id);
        if (! $leave) return $this->notFound('Pengajuan cuti');
        return $this->ok(new LeaveRequestResource($leave));
    }

    /**
     * @OA\Post(
     *   path="/hrd/leave-requests/{id}/approve",
     *   tags={"HRD"},
     *   summary="Setujui pengajuan cuti",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Cuti disetujui")
     * )
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $leave = LeaveRequest::with('employee')->find($id);
        if (! $leave) return $this->notFound('Pengajuan cuti');

        if (! $leave->isPending()) {
            return $this->error('Hanya pengajuan dengan status pending yang bisa disetujui.', 422, 'INVALID_STATUS');
        }

        $user = $request->user();

        // Level 1 approval (Manager)
        if (! $leave->approved_by_l1) {
            $leave->update([
                'approved_by_l1' => $user->id,
                'approved_at_l1' => now(),
                'status'         => LeaveRequest::STATUS_PENDING, // tunggu L2
            ]);
            return $this->ok(null, 'Persetujuan Level 1 berhasil. Menunggu persetujuan HR Manager.');
        }

        // Level 2 approval (HR Manager) → final approved
        $leave->update([
            'approved_by_l2' => $user->id,
            'approved_at_l2' => now(),
            'status'         => LeaveRequest::STATUS_APPROVED,
        ]);

        // Update leave allocation: pending → used
        $allocation = LeaveAllocation::where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('year', Carbon::parse($leave->start_date)->year)
            ->first();

        if ($allocation) {
            $allocation->decrement('pending_days', $leave->total_days);
            $allocation->increment('used_days',    $leave->total_days);
        }

        // Buat record attendance untuk tanggal cuti
        $this->createLeaveAttendances($leave, $request->user()->tenant_id);

        return $this->ok(null, 'Pengajuan cuti telah disetujui sepenuhnya.');
    }

    /**
     * @OA\Post(
     *   path="/hrd/leave-requests/{id}/reject",
     *   tags={"HRD"},
     *   summary="Tolak pengajuan cuti",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"rejection_notes"},
     *       @OA\Property(property="rejection_notes", type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Pengajuan ditolak")
     * )
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate(['rejection_notes' => ['required', 'string', 'max:500']]);

        $leave = LeaveRequest::find($id);
        if (! $leave) return $this->notFound('Pengajuan cuti');

        if (! $leave->isPending()) {
            return $this->error('Hanya pengajuan dengan status pending yang bisa ditolak.', 422, 'INVALID_STATUS');
        }

        $leave->update([
            'status'          => LeaveRequest::STATUS_REJECTED,
            'rejection_notes' => $request->rejection_notes,
        ]);

        // Kembalikan pending days ke allocation
        $allocation = LeaveAllocation::where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('year', Carbon::parse($leave->start_date)->year)
            ->first();

        if ($allocation) {
            $allocation->decrement('pending_days', $leave->total_days);
        }

        return $this->ok(null, 'Pengajuan cuti telah ditolak.');
    }

    /**
     * @OA\Post(
     *   path="/hrd/leave-requests/{id}/cancel",
     *   tags={"HRD"},
     *   summary="Batalkan pengajuan cuti",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Pengajuan dibatalkan")
     * )
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $leave = LeaveRequest::find($id);
        if (! $leave) return $this->notFound('Pengajuan cuti');

        if ($leave->isApproved()) {
            return $this->error('Cuti yang sudah disetujui tidak bisa dibatalkan langsung. Hubungi HR Manager.', 422, 'ALREADY_APPROVED');
        }

        $leave->update(['status' => LeaveRequest::STATUS_CANCELLED]);

        // Kembalikan pending days
        if ($leave->status === LeaveRequest::STATUS_PENDING) {
            $allocation = LeaveAllocation::where('employee_id', $leave->employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->where('year', Carbon::parse($leave->start_date)->year)
                ->first();

            if ($allocation) {
                $allocation->decrement('pending_days', $leave->total_days);
            }
        }

        return $this->ok(null, 'Pengajuan cuti berhasil dibatalkan.');
    }

    // ── Private Helpers ────────────────────────────────────

    private function countWorkdays(Carbon $start, Carbon $end, string $tenantId, ?WorkSchedule $schedule): float
    {
        $workDays = $schedule?->work_days ?? [1, 2, 3, 4, 5];

        $holidays = PublicHoliday::where('tenant_id', $tenantId)
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->pluck('holiday_date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        $count   = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            $dow = $current->dayOfWeek === 0 ? 7 : $current->dayOfWeek;
            if (in_array($dow, $workDays) && ! in_array($current->toDateString(), $holidays)) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    private function createLeaveAttendances(LeaveRequest $leave, string $tenantId): void
    {
        $current = Carbon::parse($leave->start_date);
        $end     = Carbon::parse($leave->end_date);

        while ($current->lte($end)) {
            Attendance::firstOrCreate(
                ['employee_id' => $leave->employee_id, 'attendance_date' => $current->toDateString()],
                [
                    'tenant_id' => $tenantId,
                    'status'    => Attendance::STATUS_LEAVE,
                    'notes'     => "Cuti: {$leave->leaveType?->name}",
                ]
            );
            $current->addDay();
        }
    }
}
