<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Building;
use App\Models\Payment;
use App\Services\PaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $service) {}

    public function index(Request $request)
    {
        $payments = Payment::with('flat.currentOwners.person', 'flat.currentTenant.person')
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

    /** The printable money receipt - only once the payment has been approved and has a receipt number. */
    public function receipt(Payment $payment)
    {
        if ($payment->status !== 'APPROVED') {
            throw ValidationException::withMessages(['status' => 'অনুমোদনের পর রসিদ পাওয়া যাবে।']);
        }
        $payment->load('flat.currentOwners.person', 'bill.billingPeriod');

        return Pdf::loadView('reports.money-receipt', [
            'building' => Building::first(),
            'payment' => $payment,
            'owner' => $payment->flat->currentOwners->map(fn ($p) => $p->person?->name_en ?: $p->person?->name_bn)->filter()->implode(', '),
            'alloc' => $this->service->allocation($payment),
        ])->setPaper('a5', 'landscape')->stream("receipt-{$payment->receipt_no}.pdf");
    }

    public function approve(Payment $payment)
    {
        $payment = $this->service->approve($payment, Auth::id());

        return new PaymentResource($payment->load('flat'));
    }
}
