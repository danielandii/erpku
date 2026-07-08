<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Api\BaseController;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $permissions = Permission::when($request->module, fn($q, $m) => $q->where('module', $m))
            ->orderBy('module')->orderBy('resource')->orderBy('action')
            ->get()
            ->groupBy('module')
            ->map(fn($items, $module) => [
                'module'      => $module,
                'label'       => ucfirst($module),
                'permissions' => $items->values(),
            ])->values();

        return $this->ok($permissions);
    }
}
