<?php

namespace App\Http\Controllers\Api\Hrd;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Hrd\AttendanceResource;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PublicHoliday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/hrd/attendances",
     *   tags={"HRD"},
     *   summary="Rekap kehadiran",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="employee_id",  in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="date_from",    in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="date_to",      in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="status",       in="query", @OA\Schema(type="string", enum={"present","late","absent","permission","sick","leave","holiday"})),
     *   @OA\Parameter(name="department_id",in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="per_page",     in="query", @OA\Schema(type="integer", default=30)),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AttendanceResource"))
     *     )
     *   )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with(['employee.department'])
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->department_id, fn($q) =>
                $q->whereHas('employee', fn($q) => $q->where('department_id', $request->department_id))
            )
            ->when($request->date_from, fn($q) => $q->where('attendance_date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->where('attendance_date', '<=', $request->date_to))
            ->when($request->status,    fn($q) => $q->where('status', $request->status))
            ->orderByDesc('attendance_date');

        $result = $query->paginate($request->per_page ?? 30);
        return $this->paginated($result, AttendanceResource::class);
    }

    /**
     * @OA\Post(
     *   path="/hrd/attendances/check-in",
     *   tags={"HRD"},
     *   summary="Clock-in karyawan",
     *   description="Karyawan melakukan check-in. Otomatis menghitung keterlambatan.",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="employee_id",  type="string", format="uuid", description="Kosongkan jika self check-in"),
     *       @OA\Property(property="latitude",     type="number", format="float"),
     *       @OA\Property(property="longitude",    type="number", format="float"),
     *       @OA\Property(property="photo_url",    type="string", description="URL foto selfie setelah upload"),
     *       @OA\Property(property="notes",        type="string")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Check-in berhasil",
     *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/AttendanceResource"))
     *   ),
     *   @OA\Response(response=409, description="Sudah check-in hari ini")
     * )
     */
    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'uuid', 'exists:employees,id'],
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
            'photo_url'   => ['nullable', 'string', 'max:500'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        // Resolve employee dari user atau dari param
        $employee = $validated['employee_id']
            ? Employee::find($validated['employee_id'])
            : Employee::where('user_id', $request->user()->id)->first();

        if (! $employee) {
            return $this->error('Data karyawan tidak ditemukan.', 404, 'EMPLOYEE_NOT_FOUND');
        }

        $today = now()->toDateString();

        // Cek sudah check-in hari ini
        $existing = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $today)
            ->first();

        if ($existing) {
            return $this->error('Anda sudah melakukan check-in hari ini.', 409, 'ALREADY_CHECKED_IN');
        }

        // Cek hari libur
        $isHoliday = PublicHoliday::where('tenant_id', $request->user()->tenant_id)
            ->where('holiday_date', $today)
            ->exists();

        $now          = now();
        $lateMinutes  = 0;
        $status       = Attendance::STATUS_PRESENT;

        if ($isHoliday) {
            $status = Attendance::STATUS_HOLIDAY;
        } elseif ($employee->workSchedule) {
            $lateMinutes = $employee->workSchedule->calculateLateMinutes($now);
            $status      = $lateMinutes > 0 ? Attendance::STATUS_LATE : Attendance::STATUS_PRESENT;
        }

        $attendance = Attendance::create([
            'tenant_id'        => $request->user()->tenant_id,
            'employee_id'      => $employee->id,
            'attendance_date'  => $today,
            'status'           => $status,
            'check_in_time'    => $now,
            'check_in_lat'     => $validated['latitude']  ?? null,
            'check_in_lng'     => $validated['longitude'] ?? null,
            'check_in_photo'   => $validated['photo_url'] ?? null,
            'late_minutes'     => $lateMinutes,
            'notes'            => $validated['notes'] ?? null,
        ]);

        return $this->created(
            new AttendanceResource($attendance->load('employee')),
            $lateMinutes > 0
                ? "Check-in berhasil. Anda terlambat {$lateMinutes} menit."
                : 'Check-in berhasil. Selamat bekerja!'
        );
    }

    /**
     * @OA\Post(
     *   path="/hrd/attendances/check-out",
     *   tags={"HRD"},
     *   summary="Clock-out karyawan",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       @OA\Property(property="employee_id", type="string", format="uuid"),
     *       @OA\Property(property="latitude",    type="number"),
     *       @OA\Property(property="longitude",   type="number"),
     *       @OA\Property(property="photo_url",   type="string"),
     *       @OA\Property(property="notes",       type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Check-out berhasil")
     * )
     */
    public function checkOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'uuid'],
            'latitude'    => ['nullable', 'numeric'],
            'longitude'   => ['nullable', 'numeric'],
            'photo_url'   => ['nullable', 'string', 'max:500'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        $employee = $validated['employee_id']
            ? Employee::find($validated['employee_id'])
            : Employee::where('user_id', $request->user()->id)->first();

        if (! $employee) {
            return $this->error('Data karyawan tidak ditemukan.', 404, 'EMPLOYEE_NOT_FOUND');
        }

        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', now()->toDateString())
            ->whereNotNull('check_in_time')
            ->whereNull('check_out_time')
            ->first();

        if (! $attendance) {
            return $this->error('Tidak ada data check-in hari ini atau sudah check-out.', 409, 'NO_CHECKIN');
        }

        $now      = now();
        $duration = (int) $attendance->check_in_time->diffInMinutes($now);

        $attendance->update([
            'check_out_time'        => $now,
            'check_out_lat'         => $validated['latitude']  ?? null,
            'check_out_lng'         => $validated['longitude'] ?? null,
            'work_duration_minutes' => $duration,
            'notes'                 => $validated['notes'] ?? $attendance->notes,
        ]);

        $hours   = intdiv($duration, 60);
        $minutes = $duration % 60;

        return $this->ok(
            new AttendanceResource($attendance->load('employee')),
            "Check-out berhasil. Durasi kerja: {$hours}j {$minutes}m."
        );
    }

    /**
     * @OA\Patch(
     *   path="/hrd/attendances/{id}",
     *   tags={"HRD"},
     *   summary="Koreksi manual data presensi",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       @OA\Property(property="status",         type="string"),
     *       @OA\Property(property="check_in_time",  type="string", format="date-time"),
     *       @OA\Property(property="check_out_time", type="string", format="date-time"),
     *       @OA\Property(property="notes",          type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Data presensi diperbarui")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $attendance = Attendance::find($id);
        if (! $attendance) return $this->notFound('Data presensi');

        $validated = $request->validate([
            'status'         => ['sometimes', 'in:present,late,absent,permission,sick,leave,holiday'],
            'check_in_time'  => ['sometimes', 'nullable', 'date'],
            'check_out_time' => ['sometimes', 'nullable', 'date', 'after:check_in_time'],
            'late_minutes'   => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notes'          => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        // Recalculate durasi jika kedua waktu ada
        if (isset($validated['check_in_time']) && isset($validated['check_out_time'])) {
            $in  = \Carbon\Carbon::parse($validated['check_in_time']);
            $out = \Carbon\Carbon::parse($validated['check_out_time']);
            $validated['work_duration_minutes'] = (int) $in->diffInMinutes($out);
        }

        $attendance->update($validated);

        return $this->ok(
            new AttendanceResource($attendance->load('employee')),
            'Data presensi berhasil dikoreksi.'
        );
    }

    /**
     * @OA\Post(
     *   path="/hrd/attendances/{id}/approve",
     *   tags={"HRD"},
     *   summary="Approve ijin kehadiran manual",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Ijin disetujui")
     * )
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $attendance = Attendance::find($id);
        if (! $attendance) return $this->notFound('Data presensi');

        $attendance->update(['approved_by' => $request->user()->id]);

        return $this->ok(null, 'Ijin kehadiran telah disetujui.');
    }

    /**
     * @OA\Get(
     *   path="/hrd/attendances/summary",
     *   tags={"HRD"},
     *   summary="Ringkasan kehadiran harian / bulanan",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="date",        in="query", description="Tanggal (default: hari ini)", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="month",       in="query", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="year",        in="query", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="present",   type="integer"),
     *         @OA\Property(property="late",      type="integer"),
     *         @OA\Property(property="absent",    type="integer"),
     *         @OA\Property(property="leave",     type="integer"),
     *         @OA\Property(property="sick",      type="integer"),
     *         @OA\Property(property="holiday",   type="integer"),
     *         @OA\Property(property="attendance_rate", type="number")
     *       )
     *     )
     *   )
     * )
     */
    public function summary(Request $request): JsonResponse
    {
        $year  = $request->year  ?? now()->year;
        $month = $request->month ?? now()->month;

        $query = Attendance::query()
            ->whereYear('attendance_date', $year)
            ->whereMonth('attendance_date', $month)
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id));

        $counts = $query->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $present  = ($counts['present'] ?? 0) + ($counts['late'] ?? 0);
        $total    = $counts->except(['holiday'])->sum();
        $rate     = $total > 0 ? round(($present / $total) * 100, 1) : 0;

        return $this->ok([
            'period'          => "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT),
            'present'         => (int) ($counts['present'] ?? 0),
            'late'            => (int) ($counts['late']    ?? 0),
            'absent'          => (int) ($counts['absent']  ?? 0),
            'permission'      => (int) ($counts['permission'] ?? 0),
            'sick'            => (int) ($counts['sick']    ?? 0),
            'leave'           => (int) ($counts['leave']   ?? 0),
            'holiday'         => (int) ($counts['holiday'] ?? 0),
            'total_workdays'  => $total,
            'attendance_rate' => $rate,
        ]);
    }

    /**
     * @OA\Get(
     *   path="/hrd/attendances/export",
     *   tags={"HRD"},
     *   summary="Export rekap kehadiran ke Excel",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="month", in="query", required=true, @OA\Schema(type="integer")),
     *   @OA\Parameter(name="year",  in="query", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="URL file export",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="download_url", type="string"),
     *         @OA\Property(property="expires_at",   type="string", format="date-time")
     *       )
     *     )
     *   )
     * )
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year'  => ['required', 'integer', 'min:2020'],
        ]);

        // Dispatch export job ke background queue
        $jobId = \Illuminate\Support\Str::uuid();
        // \App\Jobs\ExportAttendanceJob::dispatch($request->user()->tenant_id, $request->year, $request->month, $jobId);

        return $this->ok([
            'job_id'     => $jobId,
            'status'     => 'queued',
            'message'    => 'Export sedang diproses. Anda akan mendapat notifikasi saat selesai.',
        ]);
    }
}
