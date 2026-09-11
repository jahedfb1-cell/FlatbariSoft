<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index()
    {
        return AssetResource::collection(Asset::all());
    }

    public function store(StoreAssetRequest $request)
    {
        $asset = Asset::create(array_merge(['status' => 'ACTIVE'], $request->validated()));

        return new AssetResource($asset);
    }

    public function show(Asset $asset)
    {
        return new AssetResource($asset);
    }

    public function update(StoreAssetRequest $request, Asset $asset)
    {
        $asset->update($request->validated());

        return new AssetResource($asset);
    }

    public function addServiceLog(Request $request, Asset $asset)
    {
        $request->validate([
            'service_date' => 'required|date',
            'description' => 'nullable|string',
            'cost' => 'nullable|numeric|min:0',
            'technician' => 'nullable|string|max:255',
        ]);

        $log = $asset->serviceLogs()->create($request->only('service_date', 'description', 'cost', 'technician'));

        return response()->json($log, 201);
    }
}
