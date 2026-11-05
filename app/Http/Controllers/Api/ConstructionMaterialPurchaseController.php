<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConstructionMaterialPurchaseRequest;
use App\Http\Resources\ConstructionMaterialPurchaseResource;
use App\Models\ConstructionMaterialPurchase;
use App\Services\ConstructionMaterialPurchaseService;
use Illuminate\Support\Facades\Auth;

class ConstructionMaterialPurchaseController extends Controller
{
    public function __construct(private readonly ConstructionMaterialPurchaseService $service) {}

    public function index()
    {
        return ConstructionMaterialPurchaseResource::collection(
            ConstructionMaterialPurchase::with('expense')->latest('date')->get()
        );
    }

    public function update(StoreConstructionMaterialPurchaseRequest $request, ConstructionMaterialPurchase $purchase)
    {
        return new ConstructionMaterialPurchaseResource($this->service->update($purchase, $request->validated()));
    }

    public function destroy(ConstructionMaterialPurchase $purchase)
    {
        $this->service->cancel($purchase);

        return response()->json(['message' => 'নির্মাণ সামগ্রীর ক্রয় বাতিল করা হয়েছে।']);
    }

    public function store(StoreConstructionMaterialPurchaseRequest $request)
    {
        $purchase = $this->service->record($request->validated(), Auth::id());

        return new ConstructionMaterialPurchaseResource($purchase->load('expense'));
    }
}
