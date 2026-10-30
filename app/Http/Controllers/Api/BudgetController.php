<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Services\BudgetService;
use App\Services\ReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgets) {}

    private function year(Request $request): int
    {
        $v = $request->validate(['fiscal_year' => ['nullable', 'integer', 'between:2000,2100']]);

        return $v['fiscal_year'] ?? BudgetService::fiscalYearOf(now('Asia/Dhaka'));
    }

    /** ?fiscal_year=2026 means July 2026 - June 2027; defaults to the current fiscal year. */
    public function show(Request $request)
    {
        return response()->json($this->budgets->report($this->year($request)));
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

    /** Start this year's budget from another year's, optionally raised by a percentage. */
    public function copy(Request $request)
    {
        $v = $request->validate([
            'from_year' => ['required', 'integer', 'between:2000,2100'],
            'to_year' => ['required', 'integer', 'between:2000,2100'],
            'increase_percent' => ['nullable', 'numeric', 'between:-90,500'],
            'overwrite' => ['nullable', 'boolean'],
        ]);
        $written = $this->budgets->copy($v['from_year'], $v['to_year'], (string) ($v['increase_percent'] ?? 0), (bool) ($v['overwrite'] ?? false));

        return response()->json(['copied' => $written] + $this->budgets->report($v['to_year']));
    }

    public function pdf(Request $request)
    {
        $report = $this->budgets->report($this->year($request));

        return Pdf::loadView('reports.budget', ['building' => Building::first(), 'r' => $report])
            ->setPaper('a4', 'landscape')->stream("budget-{$report['label']}.pdf");
    }

    public function excel(Request $request, ReportExportService $exports)
    {
        $report = $this->budgets->report($this->year($request));
        $spreadsheet = $exports->budget($report);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, "budget-{$report['label']}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
