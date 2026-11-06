<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Support\PdfName;
use App\Services\AnnualStatementService;
use App\Services\ReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The year-end statements: cash flow, income and expenditure (profit & loss), and the balance sheet. */
class AnnualStatementController extends Controller
{
    public function __construct(private readonly AnnualStatementService $service, private readonly ReportExportService $exports) {}

    private function build(Request $request): array
    {
        $today = now('Asia/Dhaka');
        $v = $request->validate(['fiscal_year' => ['nullable', 'integer', 'between:2020,2100'], 'to' => ['nullable', 'date']]);

        return $this->service->build((int) ($v['fiscal_year'] ?? ($today->month >= 7 ? $today->year : $today->year - 1)), $v['to'] ?? null);
    }

    public function show(Request $request)
    {
        return response()->json($this->build($request));
    }

    public function pdf(Request $request)
    {
        $r = $this->build($request);

        $name = PdfName::make('Financial Statements', $r['label']);

        return PdfName::titled(Pdf::loadView('reports.annual', ['building' => Building::first(), 'r' => $r])->setPaper('a4', 'portrait'), $name)->stream($name);
    }

    public function excel(Request $request): StreamedResponse
    {
        $r = $this->build($request);
        $spreadsheet = $this->exports->annual($r);

        return response()->streamDownload(fn () => (new Xlsx($spreadsheet))->save('php://output'), "financial-statements-{$r['label']}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
