<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConstructionDueRequest;
use App\Models\ConstructionDue;
use App\Models\DueInstallment;
use App\Services\ConstructionDueService;
use Illuminate\Http\Request;

class ConstructionDueController extends Controller
{
    public function __construct(private readonly ConstructionDueService $service) {}

    public function index()
    {
        return ConstructionDue::with('installments')->get();
    }

    public function store(StoreConstructionDueRequest $request)
    {
        $due = $this->service->create(
            $request->safe()->except('installments'),
            $request->validated('installments'),
        );

        return response()->json($due, 201);
    }

    public function payInstallment(Request $request, DueInstallment $installment)
    {
        $request->validate(['amount' => 'required|numeric|min:0.01']);

        $installment = $this->service->recordInstallmentPayment($installment, (string) $request->input('amount'));

        return response()->json($installment);
    }

    public function agingReport()
    {
        return response()->json($this->service->agingReport());
    }
}
