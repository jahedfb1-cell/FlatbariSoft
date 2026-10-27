<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Requests\VacateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Flat;
use App\Models\Tenant;
use App\Services\FlatOccupancyService;

class TenantController extends Controller
{
    public function __construct(private readonly FlatOccupancyService $service) {}

    public function store(AssignTenantRequest $request, Flat $flat)
    {
        $tenant = $this->service->assignTenant(
            $flat,
            $request->validated('person'),
            $request->safe()->except('person'),
        );

        return new TenantResource($tenant->load('person'));
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant)
    {
        $tenant->update($request->validated());

        return new TenantResource($tenant->load('person'));
    }

    public function vacate(VacateTenantRequest $request, Tenant $tenant)
    {
        $tenant = $this->service->vacateTenant($tenant, $request->validated('end_date'));

        return new TenantResource($tenant->load('person'));
    }
}
