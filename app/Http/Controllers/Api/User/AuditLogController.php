<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $logs = DB::table('audit_logs')
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($request->module,        fn($q, $v) => $q->where('module', $v))
            ->when($request->user_id,       fn($q, $v) => $q->where('user_id', $v))
            ->when($request->resource_type, fn($q, $v) => $q->where('resource_type', $v))
            ->when($request->date_from,     fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date_to,       fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($logs);
    }
}
