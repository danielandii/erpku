<?php

namespace App\Http\Controllers\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    /**
     * @OA\Get(
     *   path="/dashboard",
     *   tags={"Auth"},
     *   summary="Data ringkasan KPI semua modul",
     *   description="Mengembalikan data statistik dari semua modul yang aktif untuk tenant yang login.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="hrd", type="object",
     *           @OA\Property(property="total_employees",    type="integer"),
     *           @OA\Property(property="present_today",      type="integer"),
     *           @OA\Property(property="late_today",         type="integer"),
     *           @OA\Property(property="absent_today",       type="integer"),
     *           @OA\Property(property="on_leave_today",     type="integer"),
     *           @OA\Property(property="pending_leave_requests", type="integer")
     *         ),
     *         @OA\Property(property="marketing", type="object",
     *           @OA\Property(property="active_leads",     type="integer"),
     *           @OA\Property(property="overdue_followups",type="integer"),
     *           @OA\Property(property="won_this_month",   type="integer"),
     *           @OA\Property(property="pipeline_value",   type="number"),
     *           @OA\Property(property="pipeline_by_stage",type="array", @OA\Items(type="object"))
     *         ),
     *         @OA\Property(property="finance", type="object",
     *           @OA\Property(property="revenue_this_month",     type="number"),
     *           @OA\Property(property="revenue_last_month",     type="number"),
     *           @OA\Property(property="revenue_growth_pct",     type="number"),
     *           @OA\Property(property="outstanding_ar",         type="number"),
     *           @OA\Property(property="overdue_ar",             type="number"),
     *           @OA\Property(property="outstanding_ap",         type="number"),
     *           @OA\Property(property="unpaid_invoices_count",  type="integer"),
     *           @OA\Property(property="overdue_invoices_count", type="integer")
     *         ),
     *         @OA\Property(property="project", type="object",
     *           @OA\Property(property="active_projects",    type="integer"),
     *           @OA\Property(property="total_tasks",        type="integer"),
     *           @OA\Property(property="overdue_tasks",      type="integer"),
     *           @OA\Property(property="my_tasks",           type="integer"),
     *           @OA\Property(property="completed_this_month",type="integer")
     *         ),
     *         @OA\Property(property="recent_activities", type="array",
     *           @OA\Items(
     *             @OA\Property(property="type",       type="string"),
     *             @OA\Property(property="message",    type="string"),
     *             @OA\Property(property="module",     type="string"),
     *             @OA\Property(property="created_at", type="string", format="date-time")
     *           )
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user      = $request->user();
        $tenantId  = $user->tenant_id;
        $today     = now()->toDateString();
        $thisMonth = now()->month;
        $thisYear  = now()->year;
        $lastMonth = now()->subMonth()->month;
        $lastYear  = now()->subMonth()->year;

        $enabledModules = \App\Models\TenantModuleConfig::where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->pluck('module_name')
            ->toArray();

        $data = [];

        // ── HRD Module ─────────────────────────────────────
        if (in_array('hrd', $enabledModules)) {
            $todayAttendances = Attendance::where('attendance_date', $today)->get();

            $data['hrd'] = [
                'total_employees'        => Employee::where('is_active', true)->count(),
                'present_today'          => $todayAttendances->whereIn('status', ['present'])->count(),
                'late_today'             => $todayAttendances->where('status', 'late')->count(),
                'absent_today'           => $todayAttendances->where('status', 'absent')->count(),
                'on_leave_today'         => $todayAttendances->whereIn('status', ['leave', 'sick', 'permission'])->count(),
                'pending_leave_requests' => LeaveRequest::where('status', 'pending')->count(),
                'attendance_rate_today'  => $this->calcAttendanceRate($todayAttendances),
            ];
        }

        // ── Marketing Module ───────────────────────────────
        if (in_array('marketing', $enabledModules)) {
            $activeLeads  = Lead::where('status', 'active')->get();
            $pipelineStages = \App\Models\LeadStage::where('tenant_id', $tenantId)
                ->where('is_won', false)->where('is_lost', false)->with('leads')->get();

            $data['marketing'] = [
                'active_leads'      => $activeLeads->count(),
                'overdue_followups' => Lead::where('status', 'active')
                    ->whereNotNull('next_followup_at')
                    ->where('next_followup_at', '<', now())
                    ->count(),
                'won_this_month'    => Lead::where('status', 'won')
                    ->whereMonth('won_at', $thisMonth)
                    ->whereYear('won_at', $thisYear)
                    ->count(),
                'pipeline_value'    => (float) $activeLeads->sum('expected_revenue'),
                'pipeline_by_stage' => $pipelineStages->map(fn($s) => [
                    'stage'       => $s->name,
                    'count'       => $s->leads->where('status', 'active')->count(),
                    'value'       => (float) $s->leads->where('status', 'active')->sum('expected_revenue'),
                    'color'       => $s->color,
                ]),
            ];
        }

        // ── Finance Module ────────────────────────────────
        if (in_array('finance', $enabledModules)) {
            $revenueThisMonth = Invoice::where('status', 'paid')
                ->whereMonth('invoice_date', $thisMonth)
                ->whereYear('invoice_date', $thisYear)
                ->sum('total_amount');

            $revenueLastMonth = Invoice::where('status', 'paid')
                ->whereMonth('invoice_date', $lastMonth)
                ->whereYear('invoice_date', $lastYear)
                ->sum('total_amount');

            $growthPct = $revenueLastMonth > 0
                ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
                : null;

            $outstandingAr = Invoice::whereNotIn('status', ['paid', 'cancelled', 'void', 'draft'])
                ->sum('remaining_amount');

            $overdueAr = Invoice::where('status', 'overdue')
                ->sum('remaining_amount');

            $outstandingAp = \App\Models\Bill::whereNotIn('status', ['paid', 'cancelled'])
                ->selectRaw('SUM(amount - paid_amount) as total')->value('total') ?? 0;

            $data['finance'] = [
                'revenue_this_month'     => (float) $revenueThisMonth,
                'revenue_last_month'     => (float) $revenueLastMonth,
                'revenue_growth_pct'     => $growthPct,
                'outstanding_ar'         => (float) $outstandingAr,
                'overdue_ar'             => (float) $overdueAr,
                'outstanding_ap'         => (float) $outstandingAp,
                'unpaid_invoices_count'  => Invoice::whereIn('status', ['open', 'partial_paid'])->count(),
                'overdue_invoices_count' => Invoice::where('status', 'overdue')->count(),
                'invoices_this_month'    => Invoice::whereNotIn('status', ['draft', 'cancelled'])
                    ->whereMonth('invoice_date', $thisMonth)
                    ->whereYear('invoice_date', $thisYear)
                    ->count(),
            ];
        }

        // ── Project Module ────────────────────────────────
        if (in_array('project', $enabledModules)) {
            $myTasksCount = Task::whereHas('assignees', fn($q) => $q->where('user_id', $user->id))
                ->where('is_done', false)
                ->count();

            $data['project'] = [
                'active_projects'     => Project::where('status', 'in_progress')->count(),
                'not_started'         => Project::where('status', 'not_started')->count(),
                'on_hold'             => Project::where('status', 'on_hold')->count(),
                'completed_projects'  => Project::where('status', 'completed')->count(),
                'total_tasks'         => Task::whereNull('parent_task_id')->where('is_done', false)->count(),
                'overdue_tasks'       => Task::whereNull('parent_task_id')
                    ->where('is_done', false)
                    ->where('due_date', '<', now())
                    ->count(),
                'my_tasks'            => $myTasksCount,
                'completed_this_month'=> Project::where('status', 'completed')
                    ->whereMonth('actual_end_date', $thisMonth)
                    ->whereYear('actual_end_date', $thisYear)
                    ->count(),
            ];
        }

        // ── Recent Activities (Audit Log) ─────────────────
        $data['recent_activities'] = \App\Models\AuditLog::where('tenant_id', $tenantId)
            ->with('user')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn($log) => [
                'type'       => $log->action,
                'module'     => $log->module,
                'resource'   => $log->resource_type,
                'actor'      => $log->user?->full_name ?? 'System',
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        // ── User Info ──────────────────────────────────────
        $data['user'] = [
            'id'          => $user->id,
            'full_name'   => $user->full_name,
            'permissions' => $user->getAllPermissions()->values(),
            'modules'     => $enabledModules,
        ];

        return $this->ok($data);
    }

    private function calcAttendanceRate($attendances): float
    {
        $total   = $attendances->whereNotIn('status', ['holiday'])->count();
        $present = $attendances->whereIn('status', ['present', 'late'])->count();
        return $total > 0 ? round(($present / $total) * 100, 1) : 0;
    }
}
