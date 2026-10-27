<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MonthlyClosingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonthlyClosingController extends Controller
{
    public function __construct(private readonly MonthlyClosingService $service) {}

    public function close(Request $request)
    {
        $request->validate(['year' => 'required|integer', 'month' => 'required|integer|min:1|max:12']);

        $transfer = $this->service->close($request->integer('year'), $request->integer('month'), Auth::id());

        return response()->json([
            'message' => 'মাস ক্লোজ করা হয়েছে।',
            'surplus_transferred' => $transfer?->amount,
        ]);
    }
}
