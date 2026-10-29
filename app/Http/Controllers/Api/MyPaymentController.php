<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMyPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\FlatParty;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Auth;

/** A resident's own payments: see them, download the receipt once approved, and report a bKash/Nagad/bank payment. */
class MyPaymentController extends Controller
{
    private function myFlatIds(): array
    {
        $personId = Auth::user()->person_id;

        return $personId ? FlatParty::where('person_id', $personId)->whereNull('to_date')->pluck('flat_id')->all() : [];
    }

    public function index()
    {
        return PaymentResource::collection(
            Payment::with('flat')->whereIn('flat_id', $this->myFlatIds())->latest('payment_date')->latest('id')->limit(100)->get()
        );
    }

    public function store(StoreMyPaymentRequest $request, PaymentService $payments)
    {
        $v = $request->validated();
        $v['reference_no'] = trim($v['reference_no']);

        // goes through the same checks as an office entry (cannot exceed what the bill still owes) and stays PENDING
        $payment = $payments->create($v, Auth::id());

        return (new PaymentResource($payment->load('flat')))->response()->setStatusCode(201);
    }

    public function receipt(Payment $payment, PaymentController $office)
    {
        abort_unless(in_array($payment->flat_id, $this->myFlatIds(), true), 404);

        return $office->receipt($payment);
    }
}
