<?php

namespace App\Http\Controllers\Api\Hrd;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Hrd\PayrollItemResource;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Services\Hrd\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends BaseController
{
    public function __construct(private PayrollService $payrollService) {}

    /**
     * @OA\Get(
     *   path="/hrd/payroll/periods",
     *   tags={"Payroll"},
     *   summary="Daftar periode penggajian",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="year", in="query", @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="array",
     *         @OA\Items(
     *           @OA\Property(property="id",             type="string", format="uuid"),
     *           @OA\Property(property="period_label",   type="string", example="Juni 2024"),
     *           @OA\Property(property="status",         type="string"),
     *           @OA\Property(property="total_gross",    type="number"),
     *           @OA\Property(property="total_net",      type="number"),
     *           @OA\Property(property="employee_count", type="integer"),
     *           @OA\Property(property="finalized_at",   type="string", format="date-time", nullable=true)
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function periods(Request $request): JsonResponse
    {
        $periods = PayrollPeriod::query()
            ->when($request->year, fn($q) => $q->where('period_year', $request->year))
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->paginate($request->per_page ?? 24);

        return $this->paginated($periods, \App\Http\Resources\Hrd\PayrollPeriodResource::class);
    }

    /**
     * @OA\Post(
     *   path="/hrd/payroll/periods",
     *   tags={"Payroll"},
     *   summary="Buat periode payroll baru",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"period_year","period_month"},
     *       @OA\Property(property="period_year",  type="integer", example=2024),
     *       @OA\Property(property="period_month", type="integer", example=6)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Periode berhasil dibuat")
     * )
     */
    public function createPeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_year'  => ['required', 'integer', 'min:2020'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $exists = PayrollPeriod::where('tenant_id', $request->user()->tenant_id)
            ->where('period_year',  $validated['period_year'])
            ->where('period_month', $validated['period_month'])
            ->exists();

        if ($exists) {
            return $this->error('Periode payroll ini sudah ada.', 409, 'PERIOD_EXISTS');
        }

        $period = PayrollPeriod::create([
            'tenant_id'    => $request->user()->tenant_id,
            'period_year'  => $validated['period_year'],
            'period_month' => $validated['period_month'],
            'status'       => PayrollPeriod::STATUS_DRAFT,
        ]);

        return $this->created(['id' => $period->id, 'period_label' => $period->period_label], 'Periode payroll berhasil dibuat.');
    }

    /**
     * @OA\Get(
     *   path="/hrd/payroll/periods/{id}",
     *   tags={"Payroll"},
     *   summary="Detail periode payroll beserta daftar item",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function showPeriod(string $id): JsonResponse
    {
        $period = PayrollPeriod::with(['items.employee', 'finalizedBy'])->find($id);
        if (! $period) return $this->notFound('Periode payroll');

        return $this->ok([
            'id'             => $period->id,
            'period_label'   => $period->period_label,
            'period_year'    => $period->period_year,
            'period_month'   => $period->period_month,
            'status'         => $period->status,
            'total_gross'    => (float) $period->total_gross,
            'total_deductions'=> (float) $period->total_deductions,
            'total_net'      => (float) $period->total_net,
            'employee_count' => $period->employee_count,
            'processed_at'   => $period->processed_at?->toIso8601String(),
            'finalized_at'   => $period->finalized_at?->toIso8601String(),
            'finalized_by'   => $period->finalizedBy?->full_name,
            'items'          => PayrollItemResource::collection($period->items),
        ]);
    }

    /**
     * @OA\Post(
     *   path="/hrd/payroll/periods/{id}/process",
     *   tags={"Payroll"},
     *   summary="Proses kalkulasi payroll batch",
     *   description="Menghitung gaji semua karyawan aktif untuk periode ini secara otomatis berdasarkan struktur gaji, data presensi, bonus, dan potongan.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Payroll berhasil diproses",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="processed_count", type="integer"),
     *         @OA\Property(property="total_gross",     type="number"),
     *         @OA\Property(property="total_net",       type="number")
     *       )
     *     )
     *   )
     * )
     */
    public function process(Request $request, string $id): JsonResponse
    {
        $period = PayrollPeriod::find($id);
        if (! $period) return $this->notFound('Periode payroll');

        if ($period->isFinalized()) {
            return $this->error('Periode yang sudah difinalisasi tidak bisa diproses ulang.', 422, 'PERIOD_FINALIZED');
        }

        $result = $this->payrollService->processPeriod($period);

        return $this->ok($result, 'Payroll berhasil diproses. Silakan review sebelum finalisasi.');
    }

    /**
     * @OA\Post(
     *   path="/hrd/payroll/periods/{id}/finalize",
     *   tags={"Payroll"},
     *   summary="Finalisasi payroll periode",
     *   description="Setelah finalisasi, data payroll tidak dapat diubah (immutable). Jurnal beban gaji akan dibuat otomatis di modul Finance.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Payroll berhasil difinalisasi")
     * )
     */
    public function finalize(Request $request, string $id): JsonResponse
    {
        $period = PayrollPeriod::find($id);
        if (! $period) return $this->notFound('Periode payroll');

        if ($period->isFinalized()) {
            return $this->error('Periode ini sudah difinalisasi.', 409, 'ALREADY_FINALIZED');
        }

        if ($period->status !== PayrollPeriod::STATUS_REVIEW) {
            return $this->error('Payroll harus diproses dan direview sebelum difinalisasi.', 422, 'NOT_READY');
        }

        $this->payrollService->finalizePeriod($period, $request->user());

        return $this->ok([
            'period_label'  => $period->period_label,
            'finalized_at'  => $period->finalized_at?->toIso8601String(),
            'total_net'     => (float) $period->total_net,
        ], "Payroll {$period->period_label} berhasil difinalisasi.");
    }

    /**
     * @OA\Get(
     *   path="/hrd/payroll/periods/{id}/export",
     *   tags={"Payroll"},
     *   summary="Export data transfer bank",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id",     in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="format", in="query", @OA\Schema(type="string", enum={"excel","bca","mandiri","bni"}, default="excel")),
     *   @OA\Response(response=200, description="URL download file export")
     * )
     */
    public function export(Request $request, string $id): JsonResponse
    {
        $period = PayrollPeriod::find($id);
        if (! $period) return $this->notFound('Periode payroll');

        if (! $period->isFinalized()) {
            return $this->error('Hanya payroll yang sudah difinalisasi yang bisa diekspor.', 422, 'NOT_FINALIZED');
        }

        $format = $request->format ?? 'excel';
        $jobId  = \Illuminate\Support\Str::uuid();
        // \App\Jobs\ExportPayrollJob::dispatch($period->id, $format, $jobId);

        return $this->ok([
            'job_id'  => $jobId,
            'format'  => $format,
            'status'  => 'queued',
            'message' => 'File export sedang diproses. Anda akan menerima notifikasi saat selesai.',
        ]);
    }

    /**
     * @OA\Post(
     *   path="/hrd/payroll/bonuses",
     *   tags={"Payroll"},
     *   summary="Tambah bonus karyawan",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"employee_id","bonus_type","description","amount"},
     *       @OA\Property(property="employee_id",       type="string", format="uuid"),
     *       @OA\Property(property="payroll_period_id", type="string", format="uuid", nullable=true),
     *       @OA\Property(property="bonus_type",        type="string", enum={"performance","thr","project","annual","other"}),
     *       @OA\Property(property="description",       type="string"),
     *       @OA\Property(property="amount",            type="number"),
     *       @OA\Property(property="is_taxable",        type="boolean", default=true)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Bonus berhasil ditambahkan")
     * )
     */
    public function addBonus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'       => ['required', 'uuid', 'exists:employees,id'],
            'payroll_period_id' => ['nullable', 'uuid', 'exists:payroll_periods,id'],
            'bonus_type'        => ['required', 'in:performance,thr,project,annual,other'],
            'description'       => ['required', 'string', 'max:255'],
            'amount'            => ['required', 'numeric', 'min:1'],
            'is_taxable'        => ['boolean'],
        ]);

        $bonus = \App\Models\EmployeeBonus::create(array_merge($validated, [
            'tenant_id'   => $request->user()->tenant_id,
            'approved_by' => $request->user()->id,
        ]));

        return $this->created(['id' => $bonus->id], 'Bonus berhasil ditambahkan.');
    }

    /**
     * @OA\Get(
     *   path="/hrd/payroll/items/{employeeId}",
     *   tags={"Payroll"},
     *   summary="Riwayat payroll item per karyawan",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="employeeId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function employeeItems(Request $request, string $employeeId): JsonResponse
    {
        $items = PayrollItem::with(['payrollPeriod', 'employee'])
            ->where('employee_id', $employeeId)
            ->orderByDesc(fn($q) => $q->select('period_year')->from('payroll_periods')
                ->whereColumn('payroll_periods.id', 'payroll_items.payroll_period_id'))
            ->paginate($request->per_page ?? 12);

        return $this->paginated($items, PayrollItemResource::class);
    }
}
