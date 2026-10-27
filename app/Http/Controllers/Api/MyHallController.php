<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HallQuoteRequest;
use App\Http\Requests\StoreHallBookingRequest;
use App\Http\Resources\CommunityHallResource;
use App\Http\Resources\HallBookingResource;
use App\Models\FlatParty;
use App\Models\HallBooking;
use App\Services\HallBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Owner/tenant portal side of the hall. A resident can see the calendar, get a price and ask for a
 * booking for a flat they actually live in or own; the office approves it. Never trusts a flat id from
 * the client without checking it belongs to the logged-in user (same rule as MyController).
 */
class MyHallController extends Controller
{
    public function __construct(private readonly HallBookingService $service) {}

    private function myFlatIds(): array
    {
        $personId = Auth::user()->person_id;

        return $personId ? FlatParty::where('person_id', $personId)->whereNull('to_date')->pluck('flat_id')->all() : [];
    }

    public function hall()
    {
        return new CommunityHallResource($this->service->hall());
    }

    public function availability(Request $request)
    {
        $month = $request->validate(['month' => ['required', 'date_format:Y-m']])['month'];

        return response()->json($this->service->availability($month));
    }

    public function quote(HallQuoteRequest $request)
    {
        return response()->json($this->service->quote($request->validated('slot'), true));
    }

    public function index()
    {
        $bookings = HallBooking::with('flat')
            ->where(fn ($q) => $q->whereIn('flat_id', $this->myFlatIds())->orWhere('requested_by', Auth::id()))
            ->orderByDesc('booking_date')
            ->get();

        return HallBookingResource::collection($bookings);
    }

    public function store(StoreHallBookingRequest $request)
    {
        $data = $request->validated();
        if (! in_array((int) $data['flat_id'], $this->myFlatIds(), true)) {
            throw ValidationException::withMessages(['flat_id' => 'এই ফ্ল্যাট আপনার নয়।']);
        }
        unset($data['extra_charge'], $data['discount']); // residents never set their own price

        $booking = $this->service->create($data, Auth::id());

        return (new HallBookingResource($booking->load('flat')))->response()->setStatusCode(201);
    }

    public function cancel(HallBooking $booking)
    {
        $mine = in_array($booking->flat_id, $this->myFlatIds(), true) || $booking->requested_by === Auth::id();
        if (! $mine || $booking->status !== 'PENDING') {
            throw ValidationException::withMessages(['status' => 'শুধু নিজের অপেক্ষমাণ অনুরোধ বাতিল করা যায়।']);
        }

        return new HallBookingResource($this->service->cancel($booking, 'আবেদনকারী নিজে বাতিল করেছেন', Auth::id())->load('flat'));
    }
}
