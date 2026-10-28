<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /** Home-screen numbers; each block is only included when the caller may open that part of the system. */
    public function overview(Request $request)
    {
        $v = $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100'], 'month' => ['nullable', 'integer', 'between:1,12']]);

        return response()->json($this->dashboard->overview($request->user(), $v['year'] ?? null, $v['month'] ?? null));
    }
}
