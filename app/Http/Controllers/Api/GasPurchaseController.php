<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGasPurchaseRequest;
use App\Http\Resources\GasPurchaseResource;
use App\Models\GasPurchase;
use App\Services\GasPurchaseService;
use Illuminate\Support\Facades\Auth;

class GasPurchaseController extends Controller
{
    public function __construct(private readonly GasPurchaseService $service) {}

    public function index()
    {
        return GasPurchaseResource::collection(
            GasPurchase::with('expense')->latest('date')->get()
        );
    }

    public function store(StoreGasPurchaseRequest $request)
    {
        $purchase = $this->service->record($request->validated(), Auth::id());

        return new GasPurchaseResource($purchase->load('expense'));
    }
}
