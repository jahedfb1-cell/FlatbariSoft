<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $service) {}

    public function index(Request $request)
    {
        $payments = Payment::with('flat')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return PaymentResource::collection($payments);
    }

    public function store(StorePaymentRequest $request)
    {
        $payment = $this->service->create($request->validated(), Auth::id());

        return new PaymentResource($payment);
    }

    public function approve(Payment $payment)
    {
        $payment = $this->service->approve($payment, Auth::id());

        return new PaymentResource($payment->load('flat'));
    }
}
