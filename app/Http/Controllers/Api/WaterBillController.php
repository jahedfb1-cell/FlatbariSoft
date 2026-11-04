<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWaterBillRequest;
use App\Models\WaterBill;
use App\Services\WaterBillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WaterBillController extends Controller
{
    public function __construct(private readonly WaterBillService $service) {}

    /** The twelve months of a July-June year with each month's bill and where it stands. */
    public function index(Request $request)
    {
        $today = now('Asia/Dhaka');
        $fy = (int) ($request->query('fiscal_year') ?: ($today->month >= 7 ? $today->year : $today->year - 1));

        return response()->json($this->service->months($fy));
    }

    public function store(StoreWaterBillRequest $request)
    {
        $bill = $this->service->record($request->validated() + ['attachments' => $request->file('attachments', [])], Auth::id());

        return response()->json($this->service->present($bill->load('expense')), 201);
    }

    public function update(StoreWaterBillRequest $request, WaterBill $waterBill)
    {
        $bill = $this->service->update($waterBill, $request->validated());

        return response()->json($this->service->present($bill->load('expense')));
    }
}
