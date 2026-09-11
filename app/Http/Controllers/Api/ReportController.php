<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportingService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportingService $service) {}

    public function trialBalance()
    {
        return response()->json($this->service->trialBalance());
    }

    public function incomeStatement(Request $request)
    {
        $request->validate(['fiscal_year' => 'required|string']);

        return response()->json($this->service->incomeStatement($request->string('fiscal_year')));
    }

    public function balanceSheet()
    {
        return response()->json($this->service->balanceSheet());
    }

    public function dashboard(Request $request)
    {
        $request->validate(['year' => 'required|integer', 'month' => 'required|integer|min:1|max:12']);

        return response()->json($this->service->dashboardKpis($request->integer('year'), $request->integer('month')));
    }
}
