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
        $q = trim((string) $request->query('q', ''));
        $payments = Payment::with('flat.currentOwners.person', 'flat.currentTenant.person')
            ->when($request->filled('status'), fn ($w) => $w->where('status', $request->status))
            ->when($request->filled('method'), fn ($w) => $w->where('method', $request->method))
            ->when($request->filled('from'), fn ($w) => $w->whereDate('payment_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($w) => $w->whereDate('payment_date', '<=', $request->to))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('receipt_no', 'like', "%{$q}%")
                ->orWhere('reference_no', 'like', "%{$q}%")
                ->orWhereHas('flat', fn ($fl) => $fl->where('flat_no', 'like', "%{$q}%"))))
            ->latest('payment_date')->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

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

    /** Everything an approver should see before saying yes: who paid what, how, against which bill, and which books it will be posted to. */
    public function review(Payment $payment)
    {
        $payment->load('flat.currentOwners.person', 'flat.currentTenant.person', 'bill.billingPeriod');
        $bill = $payment->bill;
        $approved = $payment->status === 'APPROVED';
        $billData = null;
        if ($bill) {
            $dueBefore = bcsub((string) $bill->total_payable, (string) $bill->paid_amount, 4);
            if ($approved) { // the bill already counts this payment
                $dueBefore = bcadd($dueBefore, (string) $payment->amount, 4);
            }
            $billData = [
                'year' => $bill->billingPeriod->year, 'month' => $bill->billingPeriod->month, 'due_date' => $bill->due_date?->toDateString(),
                'previous_due' => (string) $bill->previous_due, 'service_charge' => (string) $bill->service_charge, 'gas_amount' => (string) $bill->gas_amount,
                'other_charges' => (string) $bill->other_charges, 'late_fee' => (string) $bill->late_fee, 'total_payable' => (string) $bill->total_payable,
                'due_before' => $dueBefore, 'due_after' => bcsub($dueBefore, (string) $payment->amount, 4),
            ];
        }
        $isCash = $payment->method === 'CASH';
        $bank = $isCash ? null : \App\Models\BankAccount::first();
        $by = $payment->received_by ? \App\Models\User::find($payment->received_by) : null;

        return response()->json([
            'id' => $payment->id, 'status' => $payment->status, 'receipt_no' => $payment->receipt_no,
            'flat_no' => $payment->flat?->flat_no, 'owner' => $payment->flat?->occupantNames()['owner'], 'tenant' => $payment->flat?->occupantNames()['tenant'],
            'amount' => (string) $payment->amount, 'payment_date' => $payment->payment_date?->toDateString(), 'method' => $payment->method,
            'reference_no' => $payment->reference_no, 'bank_name' => $payment->bank_name, 'remarks' => $payment->remarks,
            'entered_by' => $by?->displayName(), 'entered_at' => $payment->created_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i'),
            'bill' => $billData,
            'split' => $bill ? $this->service->allocation($payment) : null,
            'posting' => [
                'medium' => $isCash ? 'CASH' : 'BANK',
                'account' => $bank ? $bank->bank_name.' — '.$bank->account_no : null,
                'bank_missing' => ! $isCash && ! $bank,
            ],
        ]);
    }

    public function approve(Payment $payment)
    {
        $payment = $this->service->approve($payment, Auth::id());

        return new PaymentResource($payment->load('flat'));
    }
}
