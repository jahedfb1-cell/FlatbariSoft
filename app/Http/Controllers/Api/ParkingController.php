<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AllocateParkingSlotRequest;
use App\Http\Requests\ParkingChargeActionRequest;
use App\Http\Requests\StoreParkingSlotRequest;
use App\Http\Resources\ParkingChargeResource;
use App\Models\ParkingAllocation;
use App\Models\ParkingCharge;
use App\Models\ParkingSlot;
use App\Services\ParkingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Office side of parking: slots, allocations, monthly charges, collection. Rules live in ParkingService. */
class ParkingController extends Controller
{
    public function __construct(private readonly ParkingService $service) {}

    private function slotRow(ParkingSlot $slot): array
    {
        $a = $slot->currentAllocation;

        return [
            'id' => $slot->id,
            'code' => $slot->code,
            'zone' => $slot->zone,
            'vehicle_type' => $slot->vehicle_type,
            'monthly_fee' => $slot->monthly_fee,
            'is_active' => $slot->is_active,
            'notes' => $slot->notes,
            'holder' => $a ? [
                'allocation_id' => $a->id,
                'flat_id' => $a->flat_id,
                'flat_no' => $a->flat?->flat_no,
                'person_name' => $a->person?->name_bn ?? $a->person?->name_en,
                'vehicle_no' => $a->vehicle_no,
                'from_date' => $a->from_date?->toDateString(),
                'monthly_fee' => $a->monthly_fee,
            ] : null,
        ];
    }

    public function slots()
    {
        return ParkingSlot::with('currentAllocation.flat', 'currentAllocation.person')->orderBy('code')->get()
            ->map(fn (ParkingSlot $s) => $this->slotRow($s))->values();
    }

    public function storeSlot(StoreParkingSlotRequest $request)
    {
        $slot = $this->service->createSlot($request->validated());

        return response()->json($this->slotRow($slot->load('currentAllocation.flat', 'currentAllocation.person')), 201);
    }

    public function updateSlot(StoreParkingSlotRequest $request, ParkingSlot $slot)
    {
        $slot = $this->service->updateSlot($slot, $request->validated());

        return response()->json($this->slotRow($slot->load('currentAllocation.flat', 'currentAllocation.person')));
    }

    public function allocate(AllocateParkingSlotRequest $request, ParkingSlot $slot)
    {
        $this->service->allocate($slot, $request->validated());

        return response()->json($this->slotRow($slot->fresh()->load('currentAllocation.flat', 'currentAllocation.person')), 201);
    }

    public function release(ParkingChargeActionRequest $request, ParkingAllocation $allocation)
    {
        $released = $this->service->release($allocation, $request->validated('to_date'));

        return response()->json($this->slotRow($released->slot->load('currentAllocation.flat', 'currentAllocation.person')));
    }

    public function charges(Request $request)
    {
        $request->validate(['year' => ['required', 'integer'], 'month' => ['required', 'integer', 'between:1,12']]);

        $charges = ParkingCharge::with('allocation.slot', 'allocation.flat')
            ->where('year', $request->year)->where('month', $request->month)
            ->get()->sortBy(fn ($c) => $c->allocation->slot->code)->values();

        return ParkingChargeResource::collection($charges);
    }

    public function showCharge(ParkingCharge $charge)
    {
        return new ParkingChargeResource($charge->load('allocation.slot', 'allocation.flat', 'payments'));
    }

    public function generate(ParkingChargeActionRequest $request)
    {
        return response()->json($this->service->generateCharges((int) $request->validated('year'), (int) $request->validated('month')));
    }

    public function pay(ParkingChargeActionRequest $request, ParkingCharge $charge)
    {
        $this->service->recordPayment($charge, $request->validated(), Auth::id());

        return new ParkingChargeResource($charge->fresh()->load('allocation.slot', 'allocation.flat', 'payments'));
    }

    public function summary(Request $request)
    {
        $request->validate(['year' => ['required', 'integer'], 'month' => ['required', 'integer', 'between:1,12']]);

        return response()->json($this->service->summary((int) $request->year, (int) $request->month));
    }
}
