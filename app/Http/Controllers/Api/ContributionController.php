<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContributionActionRequest;
use App\Http\Requests\StoreContributionRequest;
use App\Models\Building;
use App\Models\Contribution;
use App\Models\Flat;
use App\Services\ContributionService;
use App\Services\ReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Construction-cost collection per flat. Rules live in ContributionService. */
class ContributionController extends Controller
{
    public function __construct(private readonly ContributionService $service) {}

    public function statement()
    {
        return response()->json($this->service->statement());
    }

    public function history(Flat $flat)
    {
        return response()->json([
            'flat_no' => $flat->flat_no,
            'target' => $this->service->targetFor($flat),
            'entries' => $this->service->history($flat),
        ]);
    }

    public function store(StoreContributionRequest $request, Flat $flat)
    {
        $this->service->record($flat, $request->validated(), $request->user()->id);

        return response()->json($this->service->history($flat), 201);
    }

    public function reverse(ContributionActionRequest $request, Contribution $contribution)
    {
        $this->service->reverse($contribution, $request->validated('reason'), $request->user()->id);

        return response()->json($this->service->history($contribution->flat));
    }

    public function setFlatTarget(ContributionActionRequest $request, Flat $flat)
    {
        $v = $request->validated();
        $this->service->setFlatTarget($flat, isset($v['target_amount']) ? (string) $v['target_amount'] : null, $v['note'] ?? null);

        return response()->json(['target' => $this->service->targetFor($flat)]);
    }

    public function setDefaultTarget(ContributionActionRequest $request)
    {
        $setting = $this->service->setDefaultTarget((string) $request->validated('target_amount'));

        return response()->json(['default_target' => $setting->default_target]);
    }

    public function import(ContributionActionRequest $request)
    {
        $v = $request->validated();

        return response()->json($this->service->importOpening($v['rows'], $v['as_of'], $request->user()->id, (bool) ($v['dry_run'] ?? false)));
    }

    /** One flat owner's ledger (JSON for the screen). */
    public function ledger(Flat $flat)
    {
        return response()->json($this->service->ledger($flat));
    }

    public function ledgerPdf(Flat $flat)
    {
        return Pdf::loadView('reports.contribution-ledger', ['building' => Building::first(), 'ledgers' => [$this->service->ledger($flat)]])
            ->setPaper('a4', 'portrait')->stream("ledger-{$flat->flat_no}.pdf");
    }

    /** Every flat's ledger in one PDF, one page each - to hand out or file. */
    public function allLedgersPdf()
    {
        @set_time_limit(180);

        return Pdf::loadView('reports.contribution-ledger', ['building' => Building::first(), 'ledgers' => $this->service->allLedgers()])
            ->setPaper('a4', 'portrait')->stream('flat-ledgers.pdf');
    }

    public function pdf()
    {
        return Pdf::loadView('reports.contribution-statement', ['building' => Building::first(), 'report' => $this->service->statement()])
            ->setPaper('a4', 'landscape')->download('construction-contributions.pdf');
    }

    public function excel(ReportExportService $exports)
    {
        $spreadsheet = $exports->contributionStatement($this->service->statement());

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'construction-contributions.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
