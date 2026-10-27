<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFlatRequest;
use App\Http\Requests\UpdateFlatRequest;
use App\Http\Resources\FlatResource;
use App\Models\Flat;
use Illuminate\Http\Request;

class FlatController extends Controller
{
    public function index(Request $request)
    {
        $flats = Flat::with(['currentOwners.person', 'currentTenant.person'])
            ->when($request->filled('occupancy_status'), fn ($q) => $q->where('occupancy_status', $request->occupancy_status))
            ->when($request->filled('search'), fn ($q) => $q->where('flat_no', 'like', '%'.$request->search.'%'))
            ->orderBy('floor')
            ->orderBy('flat_no')
            ->paginate($request->integer('per_page', 50));

        return FlatResource::collection($flats);
    }

    public function store(StoreFlatRequest $request)
    {
        // Eloquent doesn't read back a column's DB-level default after an
        // insert (that needs a fresh()/refresh()) — set them explicitly so
        // the response the caller sees right away matches what's stored.
        $flat = Flat::create(array_merge([
            'occupancy_status' => 'EMPTY',
            'service_charge_rate' => 3500,
            'empty_flat_rate' => 2500,
            'status' => 'ACTIVE',
        ], $request->validated()));

        return new FlatResource($flat);
    }

    public function show(Flat $flat)
    {
        $flat->load(['currentOwners.person', 'currentTenant.person']);

        return new FlatResource($flat);
    }

    public function update(UpdateFlatRequest $request, Flat $flat)
    {
        $flat->update($request->validated());

        return new FlatResource($flat->fresh(['currentOwners.person', 'currentTenant.person']));
    }

    public function destroy(Flat $flat)
    {
        $flat->delete();

        return response()->json(['message' => 'ফ্ল্যাট মুছে ফেলা হয়েছে।']);
    }
}
