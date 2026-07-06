<?php

namespace App\Http\Controllers\Api\Hrd;

use App\Http\Controllers\Api\BaseController;
use App\Models\Attendance;
use App\Models\AttendancePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendancePermissionController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/hrd/attendance-permissions",
     *   tags={"HRD"},
     *   summary="Daftar pengajuan ijin kehadiran",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="status",      in="query", @OA\Schema(type="string", enum={"pending","approved","rejected"})),
     *   @OA\Parameter(name="date_from",   in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="date_to",     in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="per_page",    in="query", @OA\Schema(type="integer", default=20)),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $isAdmin = $user->hasAnyPermission(['hrd.attendance.approve', 'hrd.employees.read']);

        $perms = AttendancePermission::with(['employee', 'approvedBy'])
            ->when(! $isAdmin, fn($q) =>
                $q->whereHas('employee', fn($q) => $q->where('user_id', $user->id))
            )
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->status,      fn($q) => $q->where('status', $request->status))
            ->when($request->date_from,   fn($q) => $q->where('permission_date', '>=', $request->date_from))
            ->when($request->date_to,     fn($q) => $q->where('permission_date', '<=', $request->date_to))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return $this->paginated($perms, \App\Http\Resources\Hrd\AttendancePermissionResource::class);
    }

    /**
     * @OA\Post(
     *   path="/hrd/attendance-permissions",
     *   tags={"HRD"},
     *   summary="Ajukan ijin kehadiran",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"permission_date","permission_type","reason"},
     *       @OA\Property(property="employee_id",     type="string", format="uuid", nullable=true, description="Kosongkan untuk self-submit"),
     *       @OA\Property(property="permission_date", type="string", format="date"),
     *       @OA\Property(property="permission_type", type="string", enum={"sick","personal","family","official"}),
     *       @OA\Property(property="reason",          type="string"),
     *       @OA\Property(property="document_url",    type="string", nullable=true)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Ijin berhasil diajukan")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'     => ['nullable', 'uuid', 'exists:employees,id'],
            'permission_date' => ['required', 'date'],
            'permission_type' => ['required', 'in:sick,personal,family,official'],
            'reason'          => ['required', 'string', 'max:1000'],
            'document_url'    => ['nullable', 'string', 'max:500'],
        ]);

        $employee = $validated['employee_id']
            ? \App\Models\Employee::find($validated['employee_id'])
            : \App\Models\Employee::where('user_id', $request->user()->id)->first();

        if (! $employee) {
            return $this->error('Data karyawan tidak ditemukan.', 404, 'EMPLOYEE_NOT_FOUND');
        }

        // Cek sudah ada record attendance untuk tanggal ini
        $attendance = Attendance::firstOrCreate(
            ['employee_id' => $employee->id, 'attendance_date' => $validated['permission_date']],
            [
                'tenant_id' => $request->user()->tenant_id,
                'status'    => Attendance::STATUS_PERMISSION,
            ]
        );

        $perm = AttendancePermission::create([
            'tenant_id'       => $request->user()->tenant_id,
            'employee_id'     => $employee->id,
            'attendance_id'   => $attendance->id,
            'permission_date' => $validated['permission_date'],
            'permission_type' => $validated['permission_type'],
            'reason'          => $validated['reason'],
            'document_url'    => $validated['document_url'] ?? null,
            'status'          => 'pending',
        ]);

        return $this->created([
            'id'              => $perm->id,
            'permission_date' => $perm->permission_date,
            'permission_type' => $perm->permission_type,
            'status'          => $perm->status,
        ], 'Pengajuan ijin berhasil diajukan.');
    }

    /**
     * @OA\Get(
     *   path="/hrd/attendance-permissions/{id}",
     *   tags={"HRD"},
     *   summary="Detail pengajuan ijin",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $perm = AttendancePermission::with(['employee', 'attendance', 'approvedBy'])->find($id);
        if (! $perm) return $this->notFound('Pengajuan ijin');
        return $this->ok(new \App\Http\Resources\Hrd\AttendancePermissionResource($perm));
    }

    /**
     * @OA\Patch(
     *   path="/hrd/attendance-permissions/{id}",
     *   tags={"HRD"},
     *   summary="Approve atau reject pengajuan ijin",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"status"},
     *       @OA\Property(property="status",           type="string", enum={"approved","rejected"}),
     *       @OA\Property(property="rejection_notes",  type="string", nullable=true)
     *     )
     *   ),
     *   @OA\Response(response=200, description="Status ijin diperbarui")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $perm = AttendancePermission::with('attendance')->find($id);
        if (! $perm) return $this->notFound('Pengajuan ijin');

        if ($perm->status !== 'pending') {
            return $this->error('Hanya pengajuan dengan status pending yang bisa diproses.', 422, 'INVALID_STATUS');
        }

        $validated = $request->validate([
            'status'          => ['required', 'in:approved,rejected'],
            'rejection_notes' => ['nullable', 'string', 'max:500',
                'required_if:status,rejected'],
        ]);

        $perm->update(array_merge($validated, [
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]));

        // Update status attendance terkait
        if ($perm->attendance && $validated['status'] === 'approved') {
            $perm->attendance->update([
                'status'      => 'permission',
                'approved_by' => $request->user()->id,
            ]);
        } elseif ($perm->attendance && $validated['status'] === 'rejected') {
            // Kembalikan ke absent jika ijin ditolak
            if ($perm->attendance->status === 'permission') {
                $perm->attendance->update(['status' => 'absent']);
            }
        }

        $msg = $validated['status'] === 'approved'
            ? 'Pengajuan ijin telah disetujui.'
            : 'Pengajuan ijin telah ditolak.';

        return $this->ok(['id' => $perm->id, 'status' => $perm->status], $msg);
    }

    /**
     * @OA\Delete(
     *   path="/hrd/attendance-permissions/{id}",
     *   tags={"HRD"},
     *   summary="Batalkan pengajuan ijin",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Ijin dibatalkan")
     * )
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $perm = AttendancePermission::find($id);
        if (! $perm) return $this->notFound('Pengajuan ijin');

        if ($perm->status === 'approved') {
            return $this->error('Ijin yang sudah disetujui tidak dapat dibatalkan.', 422, 'ALREADY_APPROVED');
        }

        $perm->delete();
        return $this->ok(null, 'Pengajuan ijin berhasil dibatalkan.');
    }
}
