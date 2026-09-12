<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Services\AccountingService;
use App\Services\ReportExportService;
use App\Services\ReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportingService $service,
        private readonly ReportExportService $exports,
        private readonly AccountingService $accounting,
    ) {}

    public function trialBalance()
    {
        return response()->json($this->service->trialBalance());
    }

    public function incomeStatement(Request $request)
    {
        $request->validate(['fiscal_year' => 'nullable|string']);

        $fiscalYear = $request->string('fiscal_year')->toString() ?: $this->accounting->fiscalYearFor(now());

        return response()->json($this->service->incomeStatement($fiscalYear));
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

    // --- PDF exports (English-labelled — see PdfFontService) ----------

    public function trialBalancePdf()
    {
        return $this->pdf('reports.trial-balance', ['report' => $this->service->trialBalance()], 'trial-balance');
    }

    public function balanceSheetPdf()
    {
        return $this->pdf('reports.balance-sheet', ['report' => $this->service->balanceSheet()], 'balance-sheet');
    }

    public function incomeStatementPdf(Request $request)
    {
        $fiscalYear = $request->string('fiscal_year')->toString() ?: $this->accounting->fiscalYearFor(now());

        return $this->pdf('reports.income-statement', ['report' => $this->service->incomeStatement($fiscalYear)], 'income-statement');
    }

    // --- Excel exports (Bengali-labelled — spreadsheets shape their own text) --

    public function trialBalanceExcel()
    {
        return $this->excel($this->exports->trialBalance($this->service->trialBalance()), 'trial-balance');
    }

    public function balanceSheetExcel()
    {
        return $this->excel($this->exports->balanceSheet($this->service->balanceSheet()), 'balance-sheet');
    }

    public function incomeStatementExcel(Request $request)
    {
        $fiscalYear = $request->string('fiscal_year')->toString() ?: $this->accounting->fiscalYearFor(now());

        return $this->excel($this->exports->incomeStatement($this->service->incomeStatement($fiscalYear)), 'income-statement');
    }

    private function pdf(string $view, array $data, string $filename)
    {
        $data['building'] = Building::first();

        return Pdf::loadView($view, $data)->download("{$filename}.pdf");
    }

    private function excel(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
