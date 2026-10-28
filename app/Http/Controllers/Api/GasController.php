<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGasReadingRequest;
use App\Http\Resources\GasConsumerResource;
use App\Models\GasConsumer;
use App\Services\GasBillingService;
use Illuminate\Support\Facades\Auth;

class GasController extends Controller
{
    public function __construct(private readonly GasBillingService $service) {}

    public function consumers()
    {
        $this->service->syncFlatConsumers();

        // the project office first, then the flats in flat-number order
        return GasConsumerResource::collection(
            GasConsumer::with('flat')->where('active', true)->get()
                ->sortBy(fn ($c) => [$c->flat_id ? 1 : 0, $c->flat?->floor ?? 0, $c->flat?->flat_no ?? ''])->values()
        );
    }

    public function storeReading(StoreGasReadingRequest $request)
    {
        $consumer = GasConsumer::findOrFail($request->validated('gas_consumer_id'));

        $reading = $this->service->recordReading(
            $consumer,
            $request->validated('billing_period_id'),
            $request->validated('current_reading'),
            $request->validated('reading_date'),
            Auth::id(),
        );

        return response()->json([
            'id' => $reading->id,
            'previous_reading' => $reading->previous_reading,
            'current_reading' => $reading->current_reading,
            'units_used' => $reading->units_used,
        ], 201);
    }

    public function applyToBills(int $billingPeriodId)
    {
        $this->service->applyToBills($billingPeriodId);

        return response()->json(['message' => 'গ্যাস বিল সব ফ্ল্যাটের বিলে যোগ হয়েছে।']);
    }

    public function systemLoss(int $year, int $month, int $billingPeriodId)
    {
        return response()->json(['system_loss_percent' => $this->service->systemLossPercent($year, $month, $billingPeriodId)]);
    }
}
