<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Support\PdfName;
use App\Services\ReportExportService;
use App\Services\SimpleReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The plain-language reports: one endpoint shape for all of them, as a table, a PDF or an Excel sheet. */
class SimpleReportController extends Controller
{
    public function __construct(private readonly SimpleReportService $service, private readonly ReportExportService $exports) {}

    private function report(Request $request, string $type): array
    {
        abort_unless(in_array($type, SimpleReportService::TYPES, true), 404);
        $v = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'flat_id' => [Rule::requiredIf($type === 'flat'), 'nullable', 'integer', 'exists:flats,id'],
            'medium' => ['nullable', Rule::in(['CASH', 'BANK'])],
        ], ['to.after_or_equal' => 'শেষ তারিখ শুরুর তারিখের আগে হতে পারে না।', 'flat_id.required' => 'ফ্ল্যাট বাছুন।']);

        return $this->service->run($type, $v);
    }

    public function show(Request $request, string $type)
    {
        return response()->json($this->report($request, $type));
    }

    public function pdf(Request $request, string $type)
    {
        $r = $this->report($request, $type);

        $labels = ['dues' => 'Dues Report', 'purposes' => 'Collections by Purpose', 'collections' => 'Collections Report', 'expenses' => 'Expenses Report', 'flat' => 'Flat Statement', 'cashbook' => 'Cash Book'];
        $flat = $type === 'flat' ? \App\Models\Flat::find($request->query('flat_id')) : null;
        $name = $flat
            ? PdfName::forOwner($flat, 'Flat Statement '.PdfName::range($request->query('from'), $request->query('to')))
            : PdfName::make($labels[$type] ?? $type, PdfName::range($request->query('from'), $request->query('to')));

        return PdfName::titled(Pdf::loadView('reports.simple', ['building' => Building::first(), 'r' => $r])->setPaper('a4', 'landscape'), $name)->stream($name);
    }

    public function excel(Request $request, string $type): StreamedResponse
    {
        $spreadsheet = $this->exports->simple($this->report($request, $type));

        return response()->streamDownload(fn () => (new Xlsx($spreadsheet))->save('php://output'), "report-{$type}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
