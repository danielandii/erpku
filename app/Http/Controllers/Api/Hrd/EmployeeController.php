<?php

namespace App\Http\Controllers\Api\Hrd;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Hrd\StoreEmployeeRequest;
use App\Http\Requests\Hrd\UpdateEmployeeRequest;
use App\Http\Resources\Hrd\EmployeeResource;
use App\Http\Resources\Hrd\PayrollItemResource;
use App\Models\Employee;
use App\Models\LeaveAllocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/hrd/employees",
     *   tags={"HRD"},
     *   summary="Daftar karyawan",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="search",          in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="department_id",   in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="employment_type", in="query", @OA\Schema(type="string", enum={"permanent","contract","freelance","intern"})),
     *   @OA\Parameter(name="is_active",       in="query", @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="per_page",        in="query", @OA\Schema(type="integer", default=15)),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EmployeeResource")),
     *       @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *     )
     *   )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::query()
            ->with(['department', 'position', 'workSchedule', 'manager'])
            ->when($request->search, fn($q) =>
                $q->where(fn($q) =>
                    $q->where('full_name', 'like', "%{$request->search}%")
                      ->orWhere('employee_number', 'like', "%{$request->search}%")
                      ->orWhere('email_personal', 'like', "%{$request->search}%")
                )
            )
            ->when($request->department_id,   fn($q) => $q->where('department_id', $request->department_id))
            ->when($request->employment_type, fn($q) => $q->where('employment_type', $request->employment_type))
            ->when($request->has('is_active'), fn($q) =>
                $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('full_name')
            ->paginate($request->per_page ?? 15);

        return $this->paginated($employees, EmployeeResource::class);
    }

    /**
     * @OA\Post(
     *   path="/hrd/employees",
     *   tags={"HRD"},
     *   summary="Tambah karyawan baru",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"employee_number","full_name","join_date","employment_type"},
     *       @OA\Property(property="employee_number",  type="string", example="MJ-088"),
     *       @OA\Property(property="full_name",        type="string", example="Bambang Susilo"),
     *       @OA\Property(property="email_personal",   type="string", format="email"),
     *       @OA\Property(property="phone",            type="string"),
     *       @OA\Property(property="gender",           type="string", enum={"male","female"}),
     *       @OA\Property(property="birth_date",       type="string", format="date"),
     *       @OA\Property(property="join_date",        type="string", format="date"),
     *       @OA\Property(property="employment_type",  type="string", enum={"permanent","contract","freelance","intern"}),
     *       @OA\Property(property="department_id",    type="string", format="uuid"),
     *       @OA\Property(property="position_id",      type="string", format="uuid"),
     *       @OA\Property(property="work_schedule_id", type="string", format="uuid"),
     *       @OA\Property(property="manager_id",       type="string", format="uuid"),
     *       @OA\Property(property="bank_name",        type="string"),
     *       @OA\Property(property="bank_account",     type="string"),
     *       @OA\Property(property="bank_account_name",type="string")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Karyawan berhasil ditambahkan",
     *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/EmployeeResource"))
     *   )
     * )
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());

        // Auto create user account jika email tersedia
        if ($request->create_user_account && $request->email_personal) {
            $user = \App\Models\User::create([
                'tenant_id'  => $request->user()->tenant_id,
                'full_name'  => $request->full_name,
                'email'      => $request->email_personal,
                'password'   => \Illuminate\Support\Facades\Hash::make('password'),
                'employee_id'=> $employee->id,
                'is_active'  => true,
            ]);
            $employee->update(['user_id' => $user->id]);
        }

        return $this->created(
            new EmployeeResource($employee->load(['department', 'position', 'workSchedule'])),
            'Karyawan berhasil ditambahkan.'
        );
    }

    /**
     * @OA\Get(
     *   path="/hrd/employees/{id}",
     *   tags={"HRD"},
     *   summary="Detail karyawan",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/EmployeeResource"))
     *   )
     * )
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $employee = Employee::with([
            'department', 'position', 'workSchedule', 'manager',
            'user', 'salaryStructure',
        ])->find($id);

        if (! $employee) return $this->notFound('Karyawan');

        return $this->ok(new EmployeeResource($employee));
    }

    /**
     * @OA\Patch(
     *   path="/hrd/employees/{id}",
     *   tags={"HRD"},
     *   summary="Update data karyawan",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(@OA\JsonContent(@OA\Property(property="full_name", type="string"))),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function update(UpdateEmployeeRequest $request, string $id): JsonResponse
    {
        $employee = Employee::find($id);
        if (! $employee) return $this->notFound('Karyawan');

        $employee->update($request->validated());

        return $this->ok(
            new EmployeeResource($employee->load(['department', 'position'])),
            'Data karyawan berhasil diperbarui.'
        );
    }

    /**
     * @OA\Delete(
     *   path="/hrd/employees/{id}",
     *   tags={"HRD"},
     *   summary="Nonaktifkan karyawan",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Karyawan dinonaktifkan")
     * )
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $employee = Employee::find($id);
        if (! $employee) return $this->notFound('Karyawan');

        $employee->update(['is_active' => false, 'resign_date' => now()->toDateString()]);
        $employee->delete();

        return $this->ok(null, 'Karyawan berhasil dinonaktifkan.');
    }

    /**
     * @OA\Get(
     *   path="/hrd/employees/{id}/payslips",
     *   tags={"HRD"},
     *   summary="Riwayat slip gaji karyawan",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id",   in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="year", in="query", @OA\Schema(type="integer", example=2024)),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function payslips(Request $request, string $id): JsonResponse
    {
        $employee = Employee::find($id);
        if (! $employee) return $this->notFound('Karyawan');

        $items = $employee->payrollItems()
            ->with('payrollPeriod')
            ->when($request->year, fn($q) =>
                $q->whereHas('payrollPeriod', fn($q) => $q->where('period_year', $request->year))
            )
            ->orderByDesc(fn($q) => $q->select('period_year')
                ->from('payroll_periods')
                ->whereColumn('payroll_periods.id', 'payroll_items.payroll_period_id')
            )
            ->paginate($request->per_page ?? 12);

        return $this->paginated($items, PayrollItemResource::class);
    }

    /**
     * @OA\Get(
     *   path="/hrd/employees/{id}/leave-balance",
     *   tags={"HRD"},
     *   summary="Saldo cuti karyawan tahun berjalan",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id",   in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="year", in="query", @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="array",
     *         @OA\Items(
     *           @OA\Property(property="leave_type",     type="string"),
     *           @OA\Property(property="allocated_days", type="number"),
     *           @OA\Property(property="used_days",      type="number"),
     *           @OA\Property(property="pending_days",   type="number"),
     *           @OA\Property(property="remaining_days", type="number")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function leaveBalance(Request $request, string $id): JsonResponse
    {
        $employee = Employee::find($id);
        if (! $employee) return $this->notFound('Karyawan');

        $year   = $request->year ?? now()->year;
        $allocs = LeaveAllocation::with('leaveType')
            ->where('employee_id', $id)
            ->where('year', $year)
            ->get()
            ->map(fn($a) => [
                'leave_type_id'   => $a->leave_type_id,
                'leave_type'      => $a->leaveType->name,
                'code'            => $a->leaveType->code,
                'allocated_days'  => (float) $a->allocated_days,
                'carry_over_days' => (float) $a->carry_over_days,
                'used_days'       => (float) $a->used_days,
                'pending_days'    => (float) $a->pending_days,
                'remaining_days'  => (float) $a->remaining_days,
            ]);

        return $this->ok(['year' => $year, 'allocations' => $allocs]);
    }
}
