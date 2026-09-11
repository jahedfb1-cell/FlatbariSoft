<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBuildingRequest;
use App\Http\Resources\BuildingResource;
use App\Models\Building;

/**
 * Green Valley BMS manages exactly one building (PRD out-of-scope:
 * multi-building SaaS) — so unlike every other master, there is no
 * store()/destroy() here. The one building row is seeded once
 * (BuildingSeeder) and only ever edited.
 */
class BuildingController extends Controller
{
    public function index()
    {
        return BuildingResource::collection(Building::all());
    }

    public function show(Building $building)
    {
        return new BuildingResource($building);
    }

    public function update(UpdateBuildingRequest $request, Building $building)
    {
        $building->update($request->validated());

        return new BuildingResource($building);
    }
}
