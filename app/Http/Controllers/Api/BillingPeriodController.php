<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingPeriod;

class BillingPeriodController extends Controller
{
    /**
     * Find-or-create a DRAFT period for a given month — lets meter reading
     * entry (Phase 1.5) happen independently of whether bills have been
     * generated yet for that month.
     */
    public function findOrCreate(int $year, int $month)
    {
        $period = BillingPeriod::firstOrCreate(['year' => $year, 'month' => $month], ['status' => 'DRAFT']);

        return response()->json($period);
    }
}
