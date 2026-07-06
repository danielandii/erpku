<?php

namespace App\Http\Controllers\Api\Hrd;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Hrd\EmployeeResource;
use App\Models\Department;
use App\Models\LeaveAllocation;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\PublicHoliday;
use App\Models\SalaryStructure;
use App\Models\WorkSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// ══════════════════════════════════════════════════════════
// SalaryStructureController
// ══════════════════════════════════════════════════════════
class SalaryStructureController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/hrd/salary-structures/{employeeId}",
     *   tags={"HRD"},
     *   summary="Struktur gaji karyawan",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="employeeId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="id",              type="string"),
     *         @OA\Property(property="base_salary",     type="number"),
     *         @OA\Property(property="effective_date",  type="string", format="date"),
     *         @OA\Property(property="components",      type="array", @OA\Items(type="object")),
     *         @OA\Property(property="total_allowances",type="number"),
     *         @OA\Property(property="gross_estimate",  type="number")
     *       )
     *     )
     *   )
     * )
     */
    public function show(string $employeeId): JsonResponse
    {
        $structure = SalaryStructure::where('employee_id', $employeeId)
            ->whereNull('end_date')
            ->latest('effective_date')
            ->first();

        if (! $structure) {
            return $this->ok(null, 'Belum ada struktur gaji untuk karyawan ini.');
        }

        return $this->ok([
            'id'              => $structure->id,
            'employee_id'     => $structure->employee_id,
            'base_salary'     => (float) $structure->base_salary,
            'effective_date'  => $structure->effective_date?->toDateString(),
            'end_date'        => $structure->end_date?->toDateString(),
            'components'      => $structure->components ?? [],
            'total_allowances'=> (float) $structure->total_allowances,
            'gross_estimate'  => (float) $structure->base_salary + $structure->total_allowances,
            'notes'           => $structure->notes,
            'created_at'      => $structure->created_at?->toIso8601String(),
        ]);
    }

    /**
     * @OA\Post(
     *   path="/hrd/salary-structures/{employeeId}",
     *   tags={"HRD"},
     *   summary="Set / update struktur gaji karyawan",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="employeeId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"base_salary","effective_date"},
     *       @OA\Property(property="base_salary",    type="number",  example=5000000),
     *       @OA\Property(property="effective_date", type="string",  format="date"),
     *       @OA\Property(property="components", type="array",
     *         @OA\Items(
     *           @OA\Property(property="name",        type="string"),
     *           @OA\Property(property="type",        type="string", enum={"allowance","deduction","employer"}),
     *           @OA\Property(property="amount",      type="number"),
     *           @OA\Property(property="is_taxable",  type="boolean"),
     *           @OA\Property(property="is_fixed",    type="boolean")
     *         )
     *       ),
     *       @OA\Property(property="notes", type="string", nullable=true)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Struktur gaji berhasil disimpan")
     * )
     */
    public function store(Request $request, string $employeeId): JsonResponse
    {
        $validated = $request->validate([
            'base_salary'            => ['required', 'numeric', 'min:0'],
            'effective_date'         => ['required', 'date'],
            'components'             => ['nullable', 'array'],
            'components.*.name'      => ['required', 'string', 'max:100'],
            'components.*.type'      => ['required', 'in:allowance,deduction,employer'],
            'components.*.amount'    => ['required', 'numeric', 'min:0'],
            'components.*.is_taxable'=> ['sometimes', 'boolean'],
            'components.*.is_fixed'  => ['sometimes', 'boolean'],
            'notes'                  => ['nullable', 'string'],
        ]);

        // End-date struktur lama
        SalaryStructure::where('employee_id', $employeeId)
            ->whereNull('end_date')
            ->update(['end_date' => \Carbon\Carbon::parse($validated['effective_date'])->subDay()->toDateString()]);

        $structure = SalaryStructure::create([
            'tenant_id'      => $request->user()->tenant_id,
            'employee_id'    => $employeeId,
            'base_salary'    => $validated['base_salary'],
            'effective_date' => $validated['effective_date'],
            'components'     => $validated['components'] ?? [],
            'notes'          => $validated['notes'] ?? null,
            'created_by'     => $request->user()->id,
        ]);

        return $this->created([
            'id'             => $structure->id,
            'base_salary'    => (float) $structure->base_salary,
            'effective_date' => $structure->effective_date?->toDateString(),
        ], 'Struktur gaji berhasil disimpan.');
    }

    /**
     * @OA\Patch(
     *   path="/hrd/salary-structures/{id}",
     *   tags={"HRD"},
     *   summary="Update struktur gaji yang masih aktif",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $structure = SalaryStructure::find($id);
        if (! $structure) return $this->notFound('Struktur gaji');

        $validated = $request->validate([
            'base_salary'  => ['sometimes', 'numeric', 'min:0'],
            'components'   => ['sometimes', 'nullable', 'array'],
            'notes'        => ['sometimes', 'nullable', 'string'],
        ]);

        $structure->update($validated);
        return $this->ok(['id' => $structure->id], 'Struktur gaji berhasil diperbarui.');
    }
}

// ══════════════════════════════════════════════════════════
// LeaveTypeController
// ══════════════════════════════════════════════════════════
class LeaveTypeController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/hrd/leave-types",
     *   tags={"HRD"},
     *   summary="Daftar jenis cuti",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $types = LeaveType::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn($t) => [
                'id'                   => $t->id,
                'name'                 => $t->name,
                'code'                 => $t->code,
                'default_days'         => $t->default_days,
                'is_paid'              => $t->is_paid,
                'carry_over'           => $t->carry_over,
                'max_carry_over_days'  => $t->max_carry_over_days,
                'requires_document'    => $t->requires_document,
                'gender_specific'      => $t->gender_specific,
                'max_consecutive_days' => $t->max_consecutive_days,
            ]);

        return $this->ok($types);
    }

    /**
     * @OA\Post(
     *   path="/hrd/leave-types",
     *   tags={"HRD"},
     *   summary="Tambah jenis cuti baru",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"name","code","default_days"},
     *       @OA\Property(property="name",                 type="string"),
     *       @OA\Property(property="code",                 type="string"),
     *       @OA\Property(property="default_days",         type="integer"),
     *       @OA\Property(property="is_paid",              type="boolean", default=true),
     *       @OA\Property(property="carry_over",           type="boolean", default=false),
     *       @OA\Property(property="max_carry_over_days",  type="integer", nullable=true),
     *       @OA\Property(property="requires_document",    type="boolean", default=false),
     *       @OA\Property(property="gender_specific",      type="string", enum={"male","female"}, nullable=true),
     *       @OA\Property(property="max_consecutive_days", type="integer", nullable=true)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Jenis cuti berhasil ditambahkan")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name'                 => ['required', 'string', 'max:100'],
            'code'                 => ['required', 'string', 'max:20', 'alpha_dash'],
            'default_days'         => ['required', 'integer', 'min:0'],
            'is_paid'              => ['sometimes', 'boolean'],
            'carry_over'           => ['sometimes', 'boolean'],
            'max_carry_over_days'  => ['nullable', 'integer', 'min:0'],
            'requires_document'    => ['sometimes', 'boolean'],
            'gender_specific'      => ['nullable', 'in:male,female'],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1'],
        ]);

        // Cek duplikasi kode per tenant
        if (LeaveType::where('tenant_id', $tenantId)->where('code', $validated['code'])->exists()) {
            return $this->error("Kode jenis cuti '{$validated['code']}' sudah digunakan.", 409, 'CODE_EXISTS');
        }

        $leaveType = LeaveType::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'is_active' => true,
        ]));

        return $this->created(['id' => $leaveType->id, 'name' => $leaveType->name], 'Jenis cuti berhasil ditambahkan.');
    }

    /**
     * @OA\Patch(
     *   path="/hrd/leave-types/{id}",
     *   tags={"HRD"},
     *   summary="Update jenis cuti",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $type = LeaveType::find($id);
        if (! $type) return $this->notFound('Jenis cuti');

        $validated = $request->validate([
            'name'                 => ['sometimes', 'string', 'max:100'],
            'default_days'         => ['sometimes', 'integer', 'min:0'],
            'is_paid'              => ['sometimes', 'boolean'],
            'carry_over'           => ['sometimes', 'boolean'],
            'max_carry_over_days'  => ['sometimes', 'nullable', 'integer'],
            'requires_document'    => ['sometimes', 'boolean'],
            'max_consecutive_days' => ['sometimes', 'nullable', 'integer'],
            'is_active'            => ['sometimes', 'boolean'],
        ]);

        $type->update($validated);
        return $this->ok(['id' => $type->id], 'Jenis cuti berhasil diperbarui.');
    }

    /**
     * @OA\Delete(
     *   path="/hrd/leave-types/{id}",
     *   tags={"HRD"},
     *   summary="Nonaktifkan jenis cuti",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $type = LeaveType::find($id);
        if (! $type) return $this->notFound('Jenis cuti');

        if ($type->leaveRequests()->exists()) {
            $type->update(['is_active' => false]);
            return $this->ok(null, 'Jenis cuti dinonaktifkan karena sudah memiliki pengajuan.');
        }

        $type->delete();
        return $this->ok(null, 'Jenis cuti berhasil dihapus.');
    }
}

// ══════════════════════════════════════════════════════════
// LeaveAllocationController
// ══════════════════════════════════════════════════════════
class LeaveAllocationController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/hrd/leave-allocations",
     *   tags={"HRD"},
     *   summary="Daftar alokasi cuti",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="employee_id",   in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="leave_type_id", in="query", @OA\Schema(type="string", format="uuid")),
     *   @OA\Parameter(name="year",          in="query", @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $allocs = LeaveAllocation::with(['employee', 'leaveType'])
            ->when($request->employee_id,   fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->leave_type_id, fn($q) => $q->where('leave_type_id', $request->leave_type_id))
            ->when($request->year,          fn($q) => $q->where('year', $request->year))
            ->paginate($request->per_page ?? 30);

        return $this->paginated($allocs, \App\Http\Resources\Hrd\LeaveAllocationResource::class);
    }

    /**
     * @OA\Post(
     *   path="/hrd/leave-allocations",
     *   tags={"HRD"},
     *   summary="Set alokasi cuti per karyawan",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"employee_id","leave_type_id","year","allocated_days"},
     *       @OA\Property(property="employee_id",   type="string", format="uuid"),
     *       @OA\Property(property="leave_type_id", type="string", format="uuid"),
     *       @OA\Property(property="year",          type="integer"),
     *       @OA\Property(property="allocated_days",type="number"),
     *       @OA\Property(property="carry_over_days",type="number", default=0)
     *     )
     *   ),
     *   @OA\Response(response=201, description="Alokasi berhasil disimpan")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'    => ['required', 'uuid', 'exists:employees,id'],
            'leave_type_id'  => ['required', 'uuid', 'exists:leave_types,id'],
            'year'           => ['required', 'integer', 'min:2020'],
            'allocated_days' => ['required', 'numeric', 'min:0'],
            'carry_over_days'=> ['sometimes', 'numeric', 'min:0'],
        ]);

        $alloc = LeaveAllocation::updateOrCreate(
            [
                'tenant_id'      => $request->user()->tenant_id,
                'employee_id'    => $validated['employee_id'],
                'leave_type_id'  => $validated['leave_type_id'],
                'year'           => $validated['year'],
            ],
            [
                'allocated_days'  => $validated['allocated_days'],
                'carry_over_days' => $validated['carry_over_days'] ?? 0,
            ]
        );

        return $this->created(['id' => $alloc->id], 'Alokasi cuti berhasil disimpan.');
    }

    /**
     * @OA\Post(
     *   path="/hrd/leave-allocations/bulk",
     *   tags={"HRD"},
     *   summary="Alokasi cuti massal ke semua karyawan",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true,
     *     @OA\JsonContent(
     *       required={"leave_type_id","year","allocated_days"},
     *       @OA\Property(property="leave_type_id",  type="string", format="uuid"),
     *       @OA\Property(property="year",           type="integer"),
     *       @OA\Property(property="allocated_days", type="number"),
     *       @OA\Property(property="department_id",  type="string", format="uuid", nullable=true, description="Kosongkan untuk semua dept")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Alokasi massal berhasil",
     *     @OA\JsonContent(@OA\Property(property="data", type="object", @OA\Property(property="processed_count", type="integer")))
     *   )
     * )
     */
    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leave_type_id'  => ['required', 'uuid', 'exists:leave_types,id'],
            'year'           => ['required', 'integer', 'min:2020'],
            'allocated_days' => ['required', 'numeric', 'min:0'],
            'department_id'  => ['nullable', 'uuid', 'exists:departments,id'],
        ]);

        $tenantId  = $request->user()->tenant_id;
        $employees = \App\Models\Employee::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($validated['department_id'], fn($q) => $q->where('department_id', $validated['department_id']))
            ->pluck('id');

        $count = 0;
        foreach ($employees as $empId) {
            LeaveAllocation::updateOrCreate(
                ['tenant_id' => $tenantId, 'employee_id' => $empId, 'leave_type_id' => $validated['leave_type_id'], 'year' => $validated['year']],
                ['allocated_days' => $validated['allocated_days'], 'carry_over_days' => 0]
            );
            $count++;
        }

        return $this->created(['processed_count' => $count], "Alokasi cuti berhasil diterapkan ke {$count} karyawan.");
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $alloc = LeaveAllocation::find($id);
        if (! $alloc) return $this->notFound('Alokasi cuti');

        $validated = $request->validate([
            'allocated_days'  => ['sometimes', 'numeric', 'min:0'],
            'carry_over_days' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $alloc->update($validated);
        return $this->ok(['id' => $alloc->id, 'remaining_days' => (float) $alloc->remaining_days], 'Alokasi cuti diperbarui.');
    }
}

// ══════════════════════════════════════════════════════════
// DepartmentController
// ══════════════════════════════════════════════════════════
class DepartmentController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/hrd/departments",
     *   tags={"HRD"},
     *   summary="Daftar department",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $depts = Department::with(['manager', 'positions'])
            ->withCount('employees')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn($d) => [
                'id'             => $d->id,
                'name'           => $d->name,
                'code'           => $d->code,
                'parent_id'      => $d->parent_id,
                'employee_count' => $d->employees_count,
                'manager'        => $d->manager ? ['id' => $d->manager->id, 'full_name' => $d->manager->full_name] : null,
            ]);

        return $this->ok($depts);
    }

    /** @OA\Post(path="/hrd/departments", tags={"HRD"}, summary="Tambah department", security={{"bearerAuth":{}}},
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"name"}, @OA\Property(property="name",type="string"), @OA\Property(property="code",type="string",nullable=true), @OA\Property(property="parent_id",type="string",nullable=true))),
     *   @OA\Response(response=201, description="Dept berhasil ditambahkan")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'code'      => ['nullable', 'string', 'max:20'],
            'parent_id' => ['nullable', 'uuid', 'exists:departments,id'],
        ]);

        $dept = Department::create(array_merge($validated, ['tenant_id' => $request->user()->tenant_id, 'is_active' => true]));
        return $this->created(['id' => $dept->id, 'name' => $dept->name], 'Department berhasil ditambahkan.');
    }

    public function show(string $id): JsonResponse
    {
        $dept = Department::with(['manager', 'positions', 'employees', 'children'])->find($id);
        if (! $dept) return $this->notFound('Department');
        return $this->ok(['id'=>$dept->id,'name'=>$dept->name,'code'=>$dept->code,'parent_id'=>$dept->parent_id,'positions'=>$dept->positions->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'level'=>$p->level]),'employee_count'=>$dept->employees->count()]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $dept = Department::find($id);
        if (! $dept) return $this->notFound('Department');
        $dept->update($request->validate(['name'=>['sometimes','string'],'code'=>['sometimes','nullable','string'],'manager_id'=>['sometimes','nullable','uuid']]));
        return $this->ok(['id'=>$dept->id], 'Department diperbarui.');
    }

    public function destroy(string $id): JsonResponse
    {
        $dept = Department::find($id);
        if (! $dept) return $this->notFound('Department');
        if ($dept->employees()->where('is_active',true)->exists()) return $this->error('Department masih memiliki karyawan aktif.', 422, 'HAS_EMPLOYEES');
        $dept->update(['is_active' => false]);
        return $this->ok(null, 'Department dinonaktifkan.');
    }
}

// ══════════════════════════════════════════════════════════
// PositionController
// ══════════════════════════════════════════════════════════
class PositionController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $positions = Position::with('department')
            ->when($request->department_id, fn($q) => $q->where('department_id', $request->department_id))
            ->where('is_active', true)->orderBy('name')->get()
            ->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'level'=>$p->level,'department'=>$p->department?->name]);
        return $this->ok($positions);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate(['name'=>['required','string','max:100'],'department_id'=>['nullable','uuid','exists:departments,id'],'level'=>['nullable','string','max:30']]);
        $pos = Position::create(array_merge($v,['tenant_id'=>$request->user()->tenant_id,'is_active'=>true]));
        return $this->created(['id'=>$pos->id,'name'=>$pos->name], 'Jabatan berhasil ditambahkan.');
    }

    public function show(string $id): JsonResponse { $p=Position::find($id); if(!$p) return $this->notFound('Jabatan'); return $this->ok(['id'=>$p->id,'name'=>$p->name,'level'=>$p->level,'department_id'=>$p->department_id]); }

    public function update(Request $request, string $id): JsonResponse
    {
        $p=Position::find($id); if(!$p) return $this->notFound('Jabatan');
        $p->update($request->validate(['name'=>['sometimes','string'],'level'=>['sometimes','nullable','string'],'is_active'=>['sometimes','boolean']]));
        return $this->ok(['id'=>$p->id], 'Jabatan diperbarui.');
    }

    public function destroy(string $id): JsonResponse
    {
        $p=Position::find($id); if(!$p) return $this->notFound('Jabatan');
        if($p->employees()->exists()) { $p->update(['is_active'=>false]); return $this->ok(null,'Jabatan dinonaktifkan.'); }
        $p->delete(); return $this->ok(null,'Jabatan dihapus.');
    }
}

// ══════════════════════════════════════════════════════════
// WorkScheduleController
// ══════════════════════════════════════════════════════════
class WorkScheduleController extends BaseController
{
    /** @OA\Get(path="/hrd/work-schedules", tags={"HRD"}, summary="Daftar jadwal kerja", security={{"bearerAuth":{}}}, @OA\Response(response=200, description="OK")) */
    public function index(): JsonResponse
    {
        $schedules = WorkSchedule::where('is_active', true)->get()->map(fn($s)=>[
            'id'=>$s->id,'name'=>$s->name,'work_days'=>$s->work_days,
            'check_in_time'=>$s->check_in_time,'check_out_time'=>$s->check_out_time,
            'grace_period_minutes'=>$s->grace_period_minutes,'break_duration_minutes'=>$s->break_duration_minutes,
        ]);
        return $this->ok($schedules);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate(['name'=>['required','string','max:100'],'work_days'=>['required','array'],'work_days.*'=>['integer','min:1','max:7'],'check_in_time'=>['required','date_format:H:i'],'check_out_time'=>['required','date_format:H:i'],'grace_period_minutes'=>['sometimes','integer','min:0'],'break_duration_minutes'=>['sometimes','integer','min:0']]);
        $s = WorkSchedule::create(array_merge($v,['tenant_id'=>$request->user()->tenant_id,'is_active'=>true]));
        return $this->created(['id'=>$s->id,'name'=>$s->name], 'Jadwal kerja berhasil ditambahkan.');
    }

    public function show(string $id): JsonResponse { $s=WorkSchedule::find($id); if(!$s) return $this->notFound('Jadwal kerja'); return $this->ok(['id'=>$s->id,'name'=>$s->name,'work_days'=>$s->work_days,'check_in_time'=>$s->check_in_time,'check_out_time'=>$s->check_out_time]); }
    public function update(Request $request, string $id): JsonResponse { $s=WorkSchedule::find($id); if(!$s) return $this->notFound('Jadwal kerja'); $s->update($request->only(['name','work_days','check_in_time','check_out_time','grace_period_minutes','break_duration_minutes'])); return $this->ok(['id'=>$s->id], 'Jadwal kerja diperbarui.'); }
    public function destroy(string $id): JsonResponse { $s=WorkSchedule::find($id); if(!$s) return $this->notFound('Jadwal kerja'); $s->update(['is_active'=>false]); return $this->ok(null,'Jadwal kerja dinonaktifkan.'); }
}

// ══════════════════════════════════════════════════════════
// HolidayController
// ══════════════════════════════════════════════════════════
class HolidayController extends BaseController
{
    /** @OA\Get(path="/hrd/holidays", tags={"HRD"}, summary="Daftar hari libur", security={{"bearerAuth":{}}},
     *  @OA\Parameter(name="year", in="query", @OA\Schema(type="integer")),
     *  @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $holidays = PublicHoliday::when($request->year, fn($q)=>$q->whereYear('holiday_date',$request->year))
            ->orderBy('holiday_date')->get()
            ->map(fn($h)=>['id'=>$h->id,'name'=>$h->name,'holiday_date'=>$h->holiday_date,'is_recurring'=>$h->is_recurring]);
        return $this->ok($holidays);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate(['name'=>['required','string','max:100'],'holiday_date'=>['required','date'],'is_recurring'=>['sometimes','boolean']]);
        $h = PublicHoliday::create(array_merge($v,['tenant_id'=>$request->user()->tenant_id]));
        return $this->created(['id'=>$h->id], 'Hari libur berhasil ditambahkan.');
    }

    public function show(string $id): JsonResponse { $h=PublicHoliday::find($id); if(!$h) return $this->notFound('Hari libur'); return $this->ok(['id'=>$h->id,'name'=>$h->name,'holiday_date'=>$h->holiday_date,'is_recurring'=>$h->is_recurring]); }
    public function update(Request $request, string $id): JsonResponse { $h=PublicHoliday::find($id); if(!$h) return $this->notFound('Hari libur'); $h->update($request->validate(['name'=>['sometimes','string'],'holiday_date'=>['sometimes','date'],'is_recurring'=>['sometimes','boolean']])); return $this->ok(['id'=>$h->id],'Hari libur diperbarui.'); }
    public function destroy(string $id): JsonResponse { $h=PublicHoliday::find($id); if(!$h) return $this->notFound('Hari libur'); $h->delete(); return $this->ok(null,'Hari libur dihapus.'); }
}
