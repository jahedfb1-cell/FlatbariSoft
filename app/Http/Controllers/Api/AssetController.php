<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\StoreAssetServiceLogRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;

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

    public function addServiceLog(StoreAssetServiceLogRequest $request, Asset $asset)
    {
        $log = $asset->serviceLogs()->create($request->validated());

        return response()->json($log, 201);
    }
}
