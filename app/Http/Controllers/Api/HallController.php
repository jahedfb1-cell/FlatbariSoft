<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HallQuoteRequest;
use App\Http\Requests\HallStatusChangeRequest;
use App\Http\Requests\RecordHallPaymentRequest;
use App\Http\Requests\StoreHallBookingRequest;
use App\Http\Requests\UpdateHallSettingsRequest;
use App\Http\Resources\CommunityHallResource;
use App\Http\Resources\HallBookingResource;
use App\Models\CommunityHall;
use App\Models\HallBooking;
use App\Services\HallBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Office side of the community hall: settings, calendar, bookings, payments. Rules live in HallBookingService. */
class HallController extends Controller
{
    public function __construct(private readonly HallBookingService $service) {}

    public function show()
    {
        return new CommunityHallResource($this->service->hall());
    }

    public function updateSettings(UpdateHallSettingsRequest $request)
    {
        $hall = CommunityHall::orderBy('id')->firstOrFail();

        return new CommunityHallResource($this->service->updateSettings($hall, $request->validated()));
    }

    public function availability(Request $request)
    {
        $month = $request->validate(['month' => ['required', 'date_format:Y-m']])['month'];

        return response()->json($this->service->availability($month));
    }

    public function quote(HallQuoteRequest $request)
    {
        return response()->json($this->service->quote($request->validated('slot'), $request->boolean('resident')));
    }

    public function index(Request $request)
    {
        $bookings = HallBooking::with('flat')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('from'), fn ($q) => $q->where('booking_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->where('booking_date', '<=', $request->to))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('customer_name', 'like', '%'.$request->q.'%')
                ->orWhere('customer_mobile', 'like', '%'.$request->q.'%')
                ->orWhere('booking_no', 'like', '%'.$request->q.'%')))
            ->orderByDesc('booking_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return HallBookingResource::collection($bookings);
    }

    public function store(StoreHallBookingRequest $request)
    {
        $data = $request->validated();
        $canManage = $request->user()->can('hall.approve');
        if (! $canManage) {
            unset($data['extra_charge'], $data['discount']); // pricing adjustments are the manager's call
        }

        $booking = $this->service->create($data, Auth::id(), autoConfirm: $canManage);

        return (new HallBookingResource($booking->load('flat', 'payments')))->response()->setStatusCode(201);
    }

    public function showBooking(HallBooking $booking)
    {
        return new HallBookingResource($booking->load('flat', 'payments'));
    }

    public function approve(HallBooking $booking)
    {
        return new HallBookingResource($this->service->approve($booking, Auth::id())->load('flat', 'payments'));
    }

    public function reject(HallStatusChangeRequest $request, HallBooking $booking)
    {
        return new HallBookingResource($this->service->reject($booking, $request->validated('reason'), Auth::id())->load('flat', 'payments'));
    }

    public function complete(HallBooking $booking)
    {
        return new HallBookingResource($this->service->complete($booking)->load('flat', 'payments'));
    }

    public function cancel(HallStatusChangeRequest $request, HallBooking $booking)
    {
        $v = $request->validated();
        $cancelled = $this->service->cancel(
            $booking,
            $v['reason'],
            Auth::id(),
            isset($v['refund_amount']) ? (string) $v['refund_amount'] : null,
            $v['method'] ?? 'CASH',
        );

        return new HallBookingResource($cancelled->load('flat', 'payments'));
    }

    public function recordPayment(RecordHallPaymentRequest $request, HallBooking $booking)
    {
        $this->service->recordPayment($booking, $request->validated(), Auth::id());

        return new HallBookingResource($booking->fresh()->load('flat', 'payments'));
    }
}
