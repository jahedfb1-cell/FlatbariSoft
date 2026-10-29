<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BudgetService;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgets) {}

    /** ?fiscal_year=2026 means July 2026 - June 2027; defaults to the current fiscal year. */
    public function show(Request $request)
    {
        $v = $request->validate(['fiscal_year' => ['nullable', 'integer', 'between:2000,2100']]);

        return response()->json($this->budgets->report($v['fiscal_year'] ?? BudgetService::fiscalYearOf(now('Asia/Dhaka'))));
    }

    public function save(Request $request)
    {
        $v = $request->validate([
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.expense_head_id' => ['required', 'exists:expense_heads,id'],
            'items.*.amount' => ['required', 'numeric', 'min:0', 'max:1000000000'],
        ]);
        $this->budgets->save($v['fiscal_year'], $v['items']);

        return response()->json($this->budgets->report($v['fiscal_year']));
    }
}
