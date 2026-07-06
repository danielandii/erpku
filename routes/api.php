<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ERP Modular — API Routes
|--------------------------------------------------------------------------
| Base URL  : /api/v1
| Auth      : Laravel Sanctum (Bearer Token)
| Middleware: auth:sanctum   → wajib login
|             module:{name}  → modul harus aktif untuk tenant
|             permission:{p} → user harus punya permission
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ══════════════════════════════════════════════════════
    // AUTH — Public (tidak perlu login)
    // ══════════════════════════════════════════════════════
    Route::prefix('auth')->group(function () {
        Route::post('login',          [\App\Http\Controllers\Api\Auth\AuthController::class, 'login']);
        Route::post('refresh',        [\App\Http\Controllers\Api\Auth\AuthController::class, 'refresh']);
        Route::post('forgot-password',[\App\Http\Controllers\Api\Auth\AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [\App\Http\Controllers\Api\Auth\AuthController::class, 'resetPassword']);
    });

    // ══════════════════════════════════════════════════════
    // PROTECTED — Semua route di bawah ini wajib login
    // ══════════════════════════════════════════════════════
    Route::middleware('auth:sanctum')->group(function () {

        // ── Auth (protected) ───────────────────────────────
        Route::prefix('auth')->group(function () {
            Route::post('logout',     [\App\Http\Controllers\Api\Auth\AuthController::class, 'logout']);
            Route::get('me',          [\App\Http\Controllers\Api\Auth\AuthController::class, 'me']);
            Route::post('change-password', [\App\Http\Controllers\Api\Auth\AuthController::class, 'changePassword']);
            Route::get('login-history',    [\App\Http\Controllers\Api\Auth\AuthController::class, 'loginHistory']);
        });

        // ── Dashboard ──────────────────────────────────────
        Route::get('dashboard', [\App\Http\Controllers\Api\DashboardController::class, 'index']);

        // ── Notifications ──────────────────────────────────
        Route::prefix('notifications')->group(function () {
            Route::get('/',            [\App\Http\Controllers\Api\NotificationController::class, 'index']);
            Route::patch('{id}/read',  [\App\Http\Controllers\Api\NotificationController::class, 'markRead']);
            Route::post('read-all',    [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);
        });

        // ══════════════════════════════════════════════════
        // MODULE: USER (wajib, selalu aktif)
        // ══════════════════════════════════════════════════
        Route::prefix('users')->middleware('permission:user.users.read')->group(function () {
            Route::get('/',         [\App\Http\Controllers\Api\User\UserController::class, 'index']);
            Route::get('{id}',      [\App\Http\Controllers\Api\User\UserController::class, 'show']);
            Route::post('/',        [\App\Http\Controllers\Api\User\UserController::class, 'store'])
                ->middleware('permission:user.users.create');
            Route::patch('{id}',    [\App\Http\Controllers\Api\User\UserController::class, 'update'])
                ->middleware('permission:user.users.update');
            Route::delete('{id}',   [\App\Http\Controllers\Api\User\UserController::class, 'destroy'])
                ->middleware('permission:user.users.delete');
            Route::post('{id}/roles',[\App\Http\Controllers\Api\User\UserController::class, 'assignRole'])
                ->middleware('permission:user.roles.update');
        });

        Route::prefix('roles')->middleware('permission:user.roles.read')->group(function () {
            Route::get('/',                     [\App\Http\Controllers\Api\User\RoleController::class, 'index']);
            Route::get('{id}',                  [\App\Http\Controllers\Api\User\RoleController::class, 'show']);
            Route::post('/',                    [\App\Http\Controllers\Api\User\RoleController::class, 'store'])
                ->middleware('permission:user.roles.create');
            Route::patch('{id}',                [\App\Http\Controllers\Api\User\RoleController::class, 'update'])
                ->middleware('permission:user.roles.update');
            Route::delete('{id}',               [\App\Http\Controllers\Api\User\RoleController::class, 'destroy'])
                ->middleware('permission:user.roles.delete');
            Route::put('{id}/permissions',      [\App\Http\Controllers\Api\User\RoleController::class, 'syncPermissions'])
                ->middleware('permission:user.roles.update');
        });

        Route::get('permissions', [\App\Http\Controllers\Api\User\PermissionController::class, 'index'])
            ->middleware('permission:user.roles.read');

        Route::get('audit-logs', [\App\Http\Controllers\Api\User\AuditLogController::class, 'index'])
            ->middleware('permission:user.audit_logs.read');

        // ══════════════════════════════════════════════════
        // MODULE: HRD
        // ══════════════════════════════════════════════════
        Route::prefix('hrd')->middleware('module:hrd')->group(function () {

            // Departments & Positions
            Route::apiResource('departments', \App\Http\Controllers\Api\Hrd\DepartmentController::class);
            Route::apiResource('positions',   \App\Http\Controllers\Api\Hrd\PositionController::class);

            // Work Schedules
            Route::apiResource('work-schedules', \App\Http\Controllers\Api\Hrd\WorkScheduleController::class);

            // Public Holidays
            Route::apiResource('holidays', \App\Http\Controllers\Api\Hrd\HolidayController::class);

            // Employees
            Route::prefix('employees')->group(function () {
                Route::get('/',               [\App\Http\Controllers\Api\Hrd\EmployeeController::class, 'index'])
                    ->middleware('permission:hrd.employees.read');
                Route::post('/',              [\App\Http\Controllers\Api\Hrd\EmployeeController::class, 'store'])
                    ->middleware('permission:hrd.employees.create');
                Route::get('{id}',            [\App\Http\Controllers\Api\Hrd\EmployeeController::class, 'show'])
                    ->middleware('permission:hrd.employees.read');
                Route::patch('{id}',          [\App\Http\Controllers\Api\Hrd\EmployeeController::class, 'update'])
                    ->middleware('permission:hrd.employees.update');
                Route::delete('{id}',         [\App\Http\Controllers\Api\Hrd\EmployeeController::class, 'destroy'])
                    ->middleware('permission:hrd.employees.delete');
                Route::get('{id}/payslips',   [\App\Http\Controllers\Api\Hrd\EmployeeController::class, 'payslips'])
                    ->middleware('permission:hrd.payroll.read');
                Route::get('{id}/leave-balance', [\App\Http\Controllers\Api\Hrd\EmployeeController::class, 'leaveBalance'])
                    ->middleware('permission:hrd.leave.read');
            });

            // Salary Structures
            Route::prefix('salary-structures')->middleware('permission:hrd.salary.read')->group(function () {
                Route::get('{employeeId}',  [\App\Http\Controllers\Api\Hrd\SalaryStructureController::class, 'show']);
                Route::post('{employeeId}', [\App\Http\Controllers\Api\Hrd\SalaryStructureController::class, 'store'])
                    ->middleware('permission:hrd.salary.create');
                Route::patch('{id}',        [\App\Http\Controllers\Api\Hrd\SalaryStructureController::class, 'update'])
                    ->middleware('permission:hrd.salary.update');
            });

            // Attendance
            Route::prefix('attendances')->group(function () {
                Route::get('/',             [\App\Http\Controllers\Api\Hrd\AttendanceController::class, 'index'])
                    ->middleware('permission:hrd.attendance.read');
                Route::post('check-in',     [\App\Http\Controllers\Api\Hrd\AttendanceController::class, 'checkIn'])
                    ->middleware('permission:hrd.attendance.create');
                Route::post('check-out',    [\App\Http\Controllers\Api\Hrd\AttendanceController::class, 'checkOut'])
                    ->middleware('permission:hrd.attendance.create');
                Route::patch('{id}',        [\App\Http\Controllers\Api\Hrd\AttendanceController::class, 'update'])
                    ->middleware('permission:hrd.attendance.update');
                Route::post('{id}/approve', [\App\Http\Controllers\Api\Hrd\AttendanceController::class, 'approve'])
                    ->middleware('permission:hrd.attendance.approve');
                Route::get('export',        [\App\Http\Controllers\Api\Hrd\AttendanceController::class, 'export'])
                    ->middleware('permission:hrd.attendance.export');
                Route::get('summary',       [\App\Http\Controllers\Api\Hrd\AttendanceController::class, 'summary'])
                    ->middleware('permission:hrd.attendance.read');
            });

            // Attendance Permissions (Ijin)
            Route::apiResource('attendance-permissions', \App\Http\Controllers\Api\Hrd\AttendancePermissionController::class);

            // Leave Types
            Route::prefix('leave-types')->middleware('permission:hrd.leave.manage')->group(function () {
                Route::get('/',        [\App\Http\Controllers\Api\Hrd\LeaveTypeController::class, 'index']);
                Route::post('/',       [\App\Http\Controllers\Api\Hrd\LeaveTypeController::class, 'store']);
                Route::patch('{id}',   [\App\Http\Controllers\Api\Hrd\LeaveTypeController::class, 'update']);
                Route::delete('{id}',  [\App\Http\Controllers\Api\Hrd\LeaveTypeController::class, 'destroy']);
            });

            // Leave Allocations
            Route::prefix('leave-allocations')->middleware('permission:hrd.leave.manage')->group(function () {
                Route::get('/',                       [\App\Http\Controllers\Api\Hrd\LeaveAllocationController::class, 'index']);
                Route::post('/',                      [\App\Http\Controllers\Api\Hrd\LeaveAllocationController::class, 'store']);
                Route::post('bulk',                   [\App\Http\Controllers\Api\Hrd\LeaveAllocationController::class, 'bulk']);
                Route::patch('{id}',                  [\App\Http\Controllers\Api\Hrd\LeaveAllocationController::class, 'update']);
            });

            // Leave Requests
            Route::prefix('leave-requests')->group(function () {
                Route::get('/',             [\App\Http\Controllers\Api\Hrd\LeaveRequestController::class, 'index'])
                    ->middleware('permission:hrd.leave.read');
                Route::post('/',            [\App\Http\Controllers\Api\Hrd\LeaveRequestController::class, 'store'])
                    ->middleware('permission:hrd.leave.create');
                Route::get('{id}',          [\App\Http\Controllers\Api\Hrd\LeaveRequestController::class, 'show'])
                    ->middleware('permission:hrd.leave.read');
                Route::post('{id}/approve', [\App\Http\Controllers\Api\Hrd\LeaveRequestController::class, 'approve'])
                    ->middleware('permission:hrd.leave.approve');
                Route::post('{id}/reject',  [\App\Http\Controllers\Api\Hrd\LeaveRequestController::class, 'reject'])
                    ->middleware('permission:hrd.leave.approve');
                Route::post('{id}/cancel',  [\App\Http\Controllers\Api\Hrd\LeaveRequestController::class, 'cancel'])
                    ->middleware('permission:hrd.leave.create');
            });

            // Payroll
            Route::prefix('payroll')->group(function () {
                Route::get('periods',              [\App\Http\Controllers\Api\Hrd\PayrollController::class, 'periods'])
                    ->middleware('permission:hrd.payroll.read');
                Route::post('periods',             [\App\Http\Controllers\Api\Hrd\PayrollController::class, 'createPeriod'])
                    ->middleware('permission:hrd.payroll.process');
                Route::get('periods/{id}',         [\App\Http\Controllers\Api\Hrd\PayrollController::class, 'showPeriod'])
                    ->middleware('permission:hrd.payroll.read');
                Route::post('periods/{id}/process',[\App\Http\Controllers\Api\Hrd\PayrollController::class, 'process'])
                    ->middleware('permission:hrd.payroll.process');
                Route::post('periods/{id}/finalize',[\App\Http\Controllers\Api\Hrd\PayrollController::class, 'finalize'])
                    ->middleware('permission:hrd.payroll.finalize');
                Route::get('periods/{id}/export',  [\App\Http\Controllers\Api\Hrd\PayrollController::class, 'export'])
                    ->middleware('permission:hrd.payroll.export');
                Route::get('items/{employeeId}',   [\App\Http\Controllers\Api\Hrd\PayrollController::class, 'employeeItems'])
                    ->middleware('permission:hrd.payroll.read');
                Route::post('bonuses',             [\App\Http\Controllers\Api\Hrd\PayrollController::class, 'addBonus'])
                    ->middleware('permission:hrd.payroll.process');
            });
        });

        // ══════════════════════════════════════════════════
        // MODULE: MARKETING
        // ══════════════════════════════════════════════════
        Route::prefix('marketing')->middleware('module:marketing')->group(function () {

            // Lead Stages
            Route::apiResource('lead-stages', \App\Http\Controllers\Api\Marketing\LeadStageController::class)
                ->middleware('permission:marketing.leads.read');

            // Leads
            Route::prefix('leads')->group(function () {
                Route::get('/',                  [\App\Http\Controllers\Api\Marketing\LeadController::class, 'index'])
                    ->middleware('permission:marketing.leads.read');
                Route::post('/',                 [\App\Http\Controllers\Api\Marketing\LeadController::class, 'store'])
                    ->middleware('permission:marketing.leads.create');
                Route::get('{id}',               [\App\Http\Controllers\Api\Marketing\LeadController::class, 'show'])
                    ->middleware('permission:marketing.leads.read');
                Route::patch('{id}',             [\App\Http\Controllers\Api\Marketing\LeadController::class, 'update'])
                    ->middleware('permission:marketing.leads.update');
                Route::delete('{id}',            [\App\Http\Controllers\Api\Marketing\LeadController::class, 'destroy'])
                    ->middleware('permission:marketing.leads.delete');
                Route::patch('{id}/stage',       [\App\Http\Controllers\Api\Marketing\LeadController::class, 'changeStage'])
                    ->middleware('permission:marketing.leads.update');
                Route::patch('{id}/assign',      [\App\Http\Controllers\Api\Marketing\LeadController::class, 'assign'])
                    ->middleware('permission:marketing.leads.assign');
                Route::post('{id}/won',          [\App\Http\Controllers\Api\Marketing\LeadController::class, 'markWon'])
                    ->middleware('permission:marketing.leads.update');
                Route::post('{id}/lost',         [\App\Http\Controllers\Api\Marketing\LeadController::class, 'markLost'])
                    ->middleware('permission:marketing.leads.update');
                Route::post('{id}/convert',      [\App\Http\Controllers\Api\Marketing\LeadController::class, 'convert'])
                    ->middleware('permission:marketing.leads.convert');
                Route::get('{id}/activities',    [\App\Http\Controllers\Api\Marketing\LeadController::class, 'activities'])
                    ->middleware('permission:marketing.leads.read');
                Route::post('{id}/activities',   [\App\Http\Controllers\Api\Marketing\LeadController::class, 'logActivity'])
                    ->middleware('permission:marketing.leads.update');
            });

            // Quotation Templates
            Route::apiResource('quotation-templates', \App\Http\Controllers\Api\Marketing\QuotationTemplateController::class);

            // Quotations
            Route::prefix('quotations')->group(function () {
                Route::get('/',                  [\App\Http\Controllers\Api\Marketing\QuotationController::class, 'index'])
                    ->middleware('permission:marketing.quotations.read');
                Route::post('/',                 [\App\Http\Controllers\Api\Marketing\QuotationController::class, 'store'])
                    ->middleware('permission:marketing.quotations.create');
                Route::get('{id}',               [\App\Http\Controllers\Api\Marketing\QuotationController::class, 'show'])
                    ->middleware('permission:marketing.quotations.read');
                Route::patch('{id}',             [\App\Http\Controllers\Api\Marketing\QuotationController::class, 'update'])
                    ->middleware('permission:marketing.quotations.update');
                Route::delete('{id}',            [\App\Http\Controllers\Api\Marketing\QuotationController::class, 'destroy'])
                    ->middleware('permission:marketing.quotations.update');
                Route::post('{id}/send',         [\App\Http\Controllers\Api\Marketing\QuotationController::class, 'send'])
                    ->middleware('permission:marketing.quotations.send');
                Route::post('{id}/confirm',      [\App\Http\Controllers\Api\Marketing\QuotationController::class, 'confirm'])
                    ->middleware('permission:marketing.quotations.confirm');
                Route::post('{id}/revise',       [\App\Http\Controllers\Api\Marketing\QuotationController::class, 'revise'])
                    ->middleware('permission:marketing.quotations.update');
                Route::get('{id}/pdf',           [\App\Http\Controllers\Api\Marketing\QuotationController::class, 'pdf'])
                    ->middleware('permission:marketing.quotations.read');
                Route::post('{id}/to-invoice',   [\App\Http\Controllers\Api\Marketing\QuotationController::class, 'convertToInvoice'])
                    ->middleware('permission:finance.invoices.create');
            });

            // Clients
            Route::prefix('clients')->group(function () {
                Route::get('/',                  [\App\Http\Controllers\Api\Marketing\ClientController::class, 'index'])
                    ->middleware('permission:marketing.clients.read');
                Route::post('/',                 [\App\Http\Controllers\Api\Marketing\ClientController::class, 'store'])
                    ->middleware('permission:marketing.clients.create');
                Route::get('{id}',               [\App\Http\Controllers\Api\Marketing\ClientController::class, 'show'])
                    ->middleware('permission:marketing.clients.read');
                Route::patch('{id}',             [\App\Http\Controllers\Api\Marketing\ClientController::class, 'update'])
                    ->middleware('permission:marketing.clients.update');
                Route::delete('{id}',            [\App\Http\Controllers\Api\Marketing\ClientController::class, 'destroy'])
                    ->middleware('permission:marketing.clients.delete');
                Route::get('{id}/transactions',  [\App\Http\Controllers\Api\Marketing\ClientController::class, 'transactions'])
                    ->middleware('permission:marketing.clients.read');
                Route::apiResource('{clientId}/contacts', \App\Http\Controllers\Api\Marketing\ClientContactController::class)
                    ->middleware('permission:marketing.clients.read');
            });
        });

        // ══════════════════════════════════════════════════
        // MODULE: FINANCE
        // ══════════════════════════════════════════════════
        Route::prefix('finance')->middleware('module:finance')->group(function () {

            // Chart of Accounts
            Route::prefix('coa')->middleware('permission:finance.coa.read')->group(function () {
                Route::get('/',        [\App\Http\Controllers\Api\Finance\CoaController::class, 'index']);
                Route::get('tree',     [\App\Http\Controllers\Api\Finance\CoaController::class, 'tree']);
                Route::get('{id}',     [\App\Http\Controllers\Api\Finance\CoaController::class, 'show']);
                Route::post('/',       [\App\Http\Controllers\Api\Finance\CoaController::class, 'store'])
                    ->middleware('permission:finance.coa.create');
                Route::patch('{id}',   [\App\Http\Controllers\Api\Finance\CoaController::class, 'update'])
                    ->middleware('permission:finance.coa.update');
                Route::delete('{id}',  [\App\Http\Controllers\Api\Finance\CoaController::class, 'destroy'])
                    ->middleware('permission:finance.coa.update');
                Route::get('{id}/balance', [\App\Http\Controllers\Api\Finance\CoaController::class, 'balance']);
            });

            // Bank Accounts
            Route::apiResource('bank-accounts', \App\Http\Controllers\Api\Finance\BankAccountController::class)
                ->middleware('permission:finance.payments.read');

            // Invoices
            Route::prefix('invoices')->group(function () {
                Route::get('/',                  [\App\Http\Controllers\Api\Finance\InvoiceController::class, 'index'])
                    ->middleware('permission:finance.invoices.read');
                Route::post('/',                 [\App\Http\Controllers\Api\Finance\InvoiceController::class, 'store'])
                    ->middleware('permission:finance.invoices.create');
                Route::get('{id}',               [\App\Http\Controllers\Api\Finance\InvoiceController::class, 'show'])
                    ->middleware('permission:finance.invoices.read');
                Route::patch('{id}',             [\App\Http\Controllers\Api\Finance\InvoiceController::class, 'update'])
                    ->middleware('permission:finance.invoices.update');
                Route::post('{id}/send',         [\App\Http\Controllers\Api\Finance\InvoiceController::class, 'send'])
                    ->middleware('permission:finance.invoices.send');
                Route::post('{id}/cancel',       [\App\Http\Controllers\Api\Finance\InvoiceController::class, 'cancel'])
                    ->middleware('permission:finance.invoices.cancel');
                Route::post('{id}/credit-note',  [\App\Http\Controllers\Api\Finance\InvoiceController::class, 'creditNote'])
                    ->middleware('permission:finance.invoices.create');
                Route::get('{id}/pdf',           [\App\Http\Controllers\Api\Finance\InvoiceController::class, 'pdf'])
                    ->middleware('permission:finance.invoices.read');
                Route::post('{id}/reminder',     [\App\Http\Controllers\Api\Finance\InvoiceController::class, 'sendReminder'])
                    ->middleware('permission:finance.invoices.send');
            });

            // Bills (Hutang Vendor / AP)
            Route::prefix('bills')->group(function () {
                Route::get('/',              [\App\Http\Controllers\Api\Finance\BillController::class, 'index'])
                    ->middleware('permission:finance.bills.read');
                Route::post('/',             [\App\Http\Controllers\Api\Finance\BillController::class, 'store'])
                    ->middleware('permission:finance.bills.create');
                Route::get('{id}',           [\App\Http\Controllers\Api\Finance\BillController::class, 'show'])
                    ->middleware('permission:finance.bills.read');
                Route::patch('{id}',         [\App\Http\Controllers\Api\Finance\BillController::class, 'update'])
                    ->middleware('permission:finance.bills.create');
                Route::post('{id}/approve',  [\App\Http\Controllers\Api\Finance\BillController::class, 'approve'])
                    ->middleware('permission:finance.bills.approve');
                Route::post('{id}/cancel',   [\App\Http\Controllers\Api\Finance\BillController::class, 'cancel'])
                    ->middleware('permission:finance.bills.approve');
            });

            // Payments
            Route::prefix('payments')->group(function () {
                Route::get('/',        [\App\Http\Controllers\Api\Finance\PaymentController::class, 'index'])
                    ->middleware('permission:finance.payments.read');
                Route::post('/',       [\App\Http\Controllers\Api\Finance\PaymentController::class, 'store'])
                    ->middleware('permission:finance.payments.create');
                Route::get('{id}',     [\App\Http\Controllers\Api\Finance\PaymentController::class, 'show'])
                    ->middleware('permission:finance.payments.read');
                Route::delete('{id}',  [\App\Http\Controllers\Api\Finance\PaymentController::class, 'destroy'])
                    ->middleware('permission:finance.payments.create');
            });

            // Journal Entries
            Route::prefix('journals')->group(function () {
                Route::get('/',               [\App\Http\Controllers\Api\Finance\JournalController::class, 'index'])
                    ->middleware('permission:finance.journals.read');
                Route::post('/',              [\App\Http\Controllers\Api\Finance\JournalController::class, 'store'])
                    ->middleware('permission:finance.journals.create');
                Route::get('{id}',            [\App\Http\Controllers\Api\Finance\JournalController::class, 'show'])
                    ->middleware('permission:finance.journals.read');
                Route::post('{id}/post',      [\App\Http\Controllers\Api\Finance\JournalController::class, 'post'])
                    ->middleware('permission:finance.journals.post');
                Route::post('{id}/reverse',   [\App\Http\Controllers\Api\Finance\JournalController::class, 'reverse'])
                    ->middleware('permission:finance.journals.post');
            });

            // Financial Reports
            Route::prefix('reports')->middleware('permission:finance.reports.read')->group(function () {
                Route::get('profit-loss',    [\App\Http\Controllers\Api\Finance\ReportController::class, 'profitLoss']);
                Route::get('balance-sheet',  [\App\Http\Controllers\Api\Finance\ReportController::class, 'balanceSheet']);
                Route::get('trial-balance',  [\App\Http\Controllers\Api\Finance\ReportController::class, 'trialBalance']);
                Route::get('cash-flow',      [\App\Http\Controllers\Api\Finance\ReportController::class, 'cashFlow']);
                Route::get('ar-aging',       [\App\Http\Controllers\Api\Finance\ReportController::class, 'arAging']);
                Route::get('ap-aging',       [\App\Http\Controllers\Api\Finance\ReportController::class, 'apAging']);
                Route::get('general-ledger', [\App\Http\Controllers\Api\Finance\ReportController::class, 'generalLedger']);
            });

            // Export Reports
            Route::prefix('reports/export')->middleware('permission:finance.reports.export')->group(function () {
                Route::get('profit-loss',   [\App\Http\Controllers\Api\Finance\ReportController::class, 'exportProfitLoss']);
                Route::get('balance-sheet', [\App\Http\Controllers\Api\Finance\ReportController::class, 'exportBalanceSheet']);
            });
        });

        // ══════════════════════════════════════════════════
        // MODULE: PROJECT
        // ══════════════════════════════════════════════════
        Route::prefix('projects')->middleware('module:project')->group(function () {

            // Projects
            Route::get('/',              [\App\Http\Controllers\Api\Project\ProjectController::class, 'index'])
                ->middleware('permission:project.projects.read');
            Route::post('/',             [\App\Http\Controllers\Api\Project\ProjectController::class, 'store'])
                ->middleware('permission:project.projects.create');
            Route::get('{id}',           [\App\Http\Controllers\Api\Project\ProjectController::class, 'show'])
                ->middleware('permission:project.projects.read');
            Route::patch('{id}',         [\App\Http\Controllers\Api\Project\ProjectController::class, 'update'])
                ->middleware('permission:project.projects.update');
            Route::delete('{id}',        [\App\Http\Controllers\Api\Project\ProjectController::class, 'destroy'])
                ->middleware('permission:project.projects.delete');
            Route::get('{id}/kanban',    [\App\Http\Controllers\Api\Project\ProjectController::class, 'kanban'])
                ->middleware('permission:project.projects.read');
            Route::get('{id}/timeline',  [\App\Http\Controllers\Api\Project\ProjectController::class, 'timeline'])
                ->middleware('permission:project.projects.read');
            Route::get('{id}/stats',     [\App\Http\Controllers\Api\Project\ProjectController::class, 'stats'])
                ->middleware('permission:project.projects.read');

            // Project Members
            Route::prefix('{projectId}/members')->middleware('permission:project.projects.update')->group(function () {
                Route::get('/',        [\App\Http\Controllers\Api\Project\ProjectMemberController::class, 'index']);
                Route::post('/',       [\App\Http\Controllers\Api\Project\ProjectMemberController::class, 'store']);
                Route::delete('{id}',  [\App\Http\Controllers\Api\Project\ProjectMemberController::class, 'destroy']);
            });

            // Task Stages (Kanban Columns)
            Route::prefix('{projectId}/stages')->middleware('permission:project.kanban.manage')->group(function () {
                Route::get('/',        [\App\Http\Controllers\Api\Project\TaskStageController::class, 'index']);
                Route::post('/',       [\App\Http\Controllers\Api\Project\TaskStageController::class, 'store']);
                Route::patch('{id}',   [\App\Http\Controllers\Api\Project\TaskStageController::class, 'update']);
                Route::delete('{id}',  [\App\Http\Controllers\Api\Project\TaskStageController::class, 'destroy']);
                Route::post('reorder', [\App\Http\Controllers\Api\Project\TaskStageController::class, 'reorder']);
            });

            // Tasks (project-scoped listing)
            Route::get('{id}/tasks',    [\App\Http\Controllers\Api\Project\TaskController::class, 'indexByProject'])
                ->middleware('permission:project.tasks.read');
        });

        // Tasks (standalone endpoints untuk operasi per-task)
        Route::prefix('tasks')->middleware(['module:project'])->group(function () {
            Route::post('/',                    [\App\Http\Controllers\Api\Project\TaskController::class, 'store'])
                ->middleware('permission:project.tasks.create');
            Route::get('{id}',                  [\App\Http\Controllers\Api\Project\TaskController::class, 'show'])
                ->middleware('permission:project.tasks.read');
            Route::patch('{id}',                [\App\Http\Controllers\Api\Project\TaskController::class, 'update'])
                ->middleware('permission:project.tasks.update');
            Route::delete('{id}',               [\App\Http\Controllers\Api\Project\TaskController::class, 'destroy'])
                ->middleware('permission:project.tasks.delete');
            Route::post('{id}/move',            [\App\Http\Controllers\Api\Project\TaskController::class, 'move'])
                ->middleware('permission:project.tasks.update');
            Route::post('{id}/done',            [\App\Http\Controllers\Api\Project\TaskController::class, 'markDone'])
                ->middleware('permission:project.tasks.update');

            // Assignees
            Route::post('{id}/assignees',       [\App\Http\Controllers\Api\Project\TaskAssigneeController::class, 'store'])
                ->middleware('permission:project.tasks.assign');
            Route::delete('{id}/assignees/{userId}', [\App\Http\Controllers\Api\Project\TaskAssigneeController::class, 'destroy'])
                ->middleware('permission:project.tasks.assign');

            // Dependencies
            Route::post('{id}/dependencies',    [\App\Http\Controllers\Api\Project\TaskDependencyController::class, 'store'])
                ->middleware('permission:project.tasks.update');
            Route::delete('{id}/dependencies/{depId}', [\App\Http\Controllers\Api\Project\TaskDependencyController::class, 'destroy'])
                ->middleware('permission:project.tasks.update');

            // Comments
            Route::get('{id}/comments',         [\App\Http\Controllers\Api\Project\TaskCommentController::class, 'index'])
                ->middleware('permission:project.tasks.read');
            Route::post('{id}/comments',        [\App\Http\Controllers\Api\Project\TaskCommentController::class, 'store'])
                ->middleware('permission:project.tasks.update');
            Route::patch('comments/{commentId}',[\App\Http\Controllers\Api\Project\TaskCommentController::class, 'update'])
                ->middleware('permission:project.tasks.update');
            Route::delete('comments/{commentId}',[\App\Http\Controllers\Api\Project\TaskCommentController::class, 'destroy'])
                ->middleware('permission:project.tasks.update');

            // Checklists
            Route::get('{id}/checklists',       [\App\Http\Controllers\Api\Project\TaskChecklistController::class, 'index'])
                ->middleware('permission:project.tasks.read');
            Route::post('{id}/checklists',      [\App\Http\Controllers\Api\Project\TaskChecklistController::class, 'store'])
                ->middleware('permission:project.tasks.update');
            Route::patch('checklists/{checkId}',[\App\Http\Controllers\Api\Project\TaskChecklistController::class, 'update'])
                ->middleware('permission:project.tasks.update');
            Route::delete('checklists/{checkId}',[\App\Http\Controllers\Api\Project\TaskChecklistController::class, 'destroy'])
                ->middleware('permission:project.tasks.update');
            Route::post('checklists/{checkId}/toggle', [\App\Http\Controllers\Api\Project\TaskChecklistController::class, 'toggle'])
                ->middleware('permission:project.tasks.update');

            // Attachments
            Route::post('{id}/attachments',     [\App\Http\Controllers\Api\Project\TaskAttachmentController::class, 'store'])
                ->middleware('permission:project.tasks.update');
            Route::delete('attachments/{attId}',[\App\Http\Controllers\Api\Project\TaskAttachmentController::class, 'destroy'])
                ->middleware('permission:project.tasks.update');

            // Time Logs
            Route::get('{id}/time-logs',        [\App\Http\Controllers\Api\Project\TaskTimeLogController::class, 'index'])
                ->middleware('permission:project.time_logs.read');
            Route::post('{id}/time-logs',       [\App\Http\Controllers\Api\Project\TaskTimeLogController::class, 'store'])
                ->middleware('permission:project.time_logs.create');
            Route::patch('time-logs/{logId}',   [\App\Http\Controllers\Api\Project\TaskTimeLogController::class, 'update'])
                ->middleware('permission:project.time_logs.create');
            Route::delete('time-logs/{logId}',  [\App\Http\Controllers\Api\Project\TaskTimeLogController::class, 'destroy'])
                ->middleware('permission:project.time_logs.create');
        });

        // ══════════════════════════════════════════════════
        // SETTINGS (Admin only)
        // ══════════════════════════════════════════════════
        Route::prefix('settings')->middleware('permission:user.users.update')->group(function () {
            Route::get('/',             [\App\Http\Controllers\Api\SettingController::class, 'index']);
            Route::patch('/',           [\App\Http\Controllers\Api\SettingController::class, 'update']);
            Route::get('modules',       [\App\Http\Controllers\Api\SettingController::class, 'modules']);
            Route::patch('modules/{module}', [\App\Http\Controllers\Api\SettingController::class, 'toggleModule']);
        });

    }); // end auth:sanctum

}); // end v1
