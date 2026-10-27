<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveRoleRequest;
use App\Services\RoleManagementService;

class RoleController extends Controller
{
    public function __construct(private readonly RoleManagementService $service) {}

    public function index()
    {
        return response()->json(['roles' => $this->service->list(), 'catalog' => $this->service->catalog()]);
    }

    public function store(SaveRoleRequest $request)
    {
        $this->service->create($request->validated('name'), $request->validated('permissions'));

        return response()->json(['roles' => $this->service->list()], 201);
    }

    public function updatePermissions(SaveRoleRequest $request, string $role)
    {
        $this->service->syncPermissions($role, $request->validated('permissions'));

        return response()->json(['roles' => $this->service->list()]);
    }

    public function destroy(string $role)
    {
        $this->service->delete($role);

        return response()->json(['roles' => $this->service->list()]);
    }
}
