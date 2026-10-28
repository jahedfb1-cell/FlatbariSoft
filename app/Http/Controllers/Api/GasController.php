<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GasConsumerRequest;
use App\Http\Requests\StoreGasReadingRequest;
use App\Http\Resources\GasConsumerResource;
use App\Models\GasConsumer;
use App\Services\GasBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GasController extends Controller
{
    public function __construct(private readonly GasBillingService $service) {}

    public function consumers(Request $request)
    {
        $this->service->syncFlatConsumers();

        // the project office first, then the flats in flat-number order
        return GasConsumerResource::collection(
            GasConsumer::with('flat')->when(! $request->boolean('all'), fn ($q) => $q->where('active', true))->get()
                ->sortBy(fn ($c) => [$c->flat_id ? 1 : 0, $c->flat?->floor ?? 0, $c->flat?->flat_no ?? ''])->values()
        );
    }

    /** Add a gas connection by hand: a flat's meter (flat_id) or any other consumer such as the project office. */
    public function storeConsumer(GasConsumerRequest $request)
    {
        $data = $request->validated();
        // a flat's consumer is never internal; an internal one never belongs to a flat
        $internal = empty($data['flat_id']) && ! empty($data['is_internal']);

        $consumer = GasConsumer::create([
            'flat_id' => $data['flat_id'] ?? null,
            'name' => $data['name'],
            'meter_no' => $data['meter_no'] ?? null,
            'is_internal' => $internal,
            'active' => $data['active'] ?? true,
        ]);

        return (new GasConsumerResource($consumer->load('flat')))->response()->setStatusCode(201);
    }

    /** Rename, change the meter number, or switch a consumer off (its readings are kept). */
    public function updateConsumer(GasConsumerRequest $request, GasConsumer $consumer)
    {
        $consumer->update($request->validated());

        return new GasConsumerResource($consumer->load('flat'));
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

    /** Top-of-page numbers: total purchases and what the flats used this month (?year=&month=, default this month). */
    public function summary(Request $request)
    {
        $now = now('Asia/Dhaka');

        return response()->json($this->service->summary(
            (int) $request->query('year', $now->year),
            (int) $request->query('month', $now->month),
        ));
    }

    public function systemLoss(int $year, int $month, int $billingPeriodId)
    {
        return response()->json(['system_loss_percent' => $this->service->systemLossPercent($year, $month, $billingPeriodId)]);
    }
}
