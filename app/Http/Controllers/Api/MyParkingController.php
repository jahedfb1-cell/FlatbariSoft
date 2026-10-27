<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParkingChargeResource;
use App\Models\FlatParty;
use App\Models\ParkingAllocation;
use App\Models\ParkingCharge;
use Illuminate\Support\Facades\Auth;

/** What a resident sees of parking: the slots held by their own flat(s) and what is owed on them. */
class MyParkingController extends Controller
{
    private function myFlatIds(): array
    {
        $personId = Auth::user()->person_id;

        return $personId ? FlatParty::where('person_id', $personId)->whereNull('to_date')->pluck('flat_id')->all() : [];
    }

    public function index()
    {
        $flatIds = $this->myFlatIds();

        $slots = ParkingAllocation::with('slot', 'flat')->whereIn('flat_id', $flatIds)->whereNull('to_date')->get()
            ->map(fn ($a) => [
                'allocation_id' => $a->id,
                'slot_code' => $a->slot->code,
                'zone' => $a->slot->zone,
                'vehicle_type' => $a->slot->vehicle_type,
                'vehicle_no' => $a->vehicle_no,
                'flat_no' => $a->flat?->flat_no,
                'monthly_fee' => $a->monthly_fee,
            ])->values();

        $charges = ParkingCharge::with('allocation.slot', 'allocation.flat', 'payments')
            ->whereHas('allocation', fn ($q) => $q->whereIn('flat_id', $flatIds))
            ->orderByDesc('year')->orderByDesc('month')->limit(24)->get();

        return response()->json(['slots' => $slots, 'charges' => ParkingChargeResource::collection($charges)->resolve()]);
    }
}
