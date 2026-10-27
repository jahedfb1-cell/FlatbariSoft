<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateBillingPeriodRequest;
use App\Http\Resources\BillResource;
use App\Models\BillingPeriod;
use App\Services\BillGenerationService;
use Illuminate\Support\Facades\Auth;

class BillingController extends Controller
{
    public function __construct(private readonly BillGenerationService $service) {}

    public function generate(GenerateBillingPeriodRequest $request)
    {
        $period = $this->service->generate(
            $request->validated('year'),
            $request->validated('month'),
            Auth::id(),
        );

        return response()->json([
            'message' => 'বিল জেনারেট হয়েছে।',
            'period_id' => $period->id,
            'bill_count' => $period->bills()->count(),
        ], 201);
    }

    public function show(int $year, int $month)
    {
        $period = BillingPeriod::where('year', $year)->where('month', $month)->firstOrFail();

        return BillResource::collection($period->bills()->with('flat')->orderBy('flat_id')->get());
    }
}
