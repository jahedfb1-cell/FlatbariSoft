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

/**
 * Collections from flat owners, kept in separate books (construction cost, flat, parking sale).
 * Rules live in ContributionService; {book} in a route is the book's code.
 */
class ContributionController extends Controller
{
    public function __construct(private readonly ContributionService $service) {}

    public function books()
    {
        return response()->json($this->service->books()->load(['heads' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id')])->map(fn ($b) => [
            'code' => $b->code, 'name_bn' => $b->name_bn, 'name_en' => $b->name_en, 'has_target' => $b->hasTarget(),
            'heads' => $b->heads->map(fn ($h) => ['id' => $h->id, 'code' => $h->code, 'name_bn' => $h->name_bn, 'name_en' => $h->name_en])->values(),
        ])->values());
    }

    public function statement(string $book)
    {
        return response()->json($this->service->statement($this->service->book($book)));
    }

    public function store(StoreContributionRequest $request, Flat $flat)
    {
        $this->service->record($flat, $request->validated(), $request->user()->id);

        return response()->json($this->service->flatLedgers($flat), 201);
    }

    public function reverse(ContributionActionRequest $request, Contribution $contribution)
    {
        $this->service->reverse($contribution, $request->validated('reason'), $request->user()->id);

        return response()->json($this->service->flatLedgers($contribution->flat));
    }

    public function setFlatTarget(ContributionActionRequest $request, string $book, Flat $flat)
    {
        $b = $this->service->book($book);
        $v = $request->validated();
        $this->service->setFlatTarget($flat, $b, isset($v['target_amount']) ? (string) $v['target_amount'] : null, $v['note'] ?? null);

        return response()->json(['target' => $this->service->targetFor($flat, $b)]);
    }

    public function setDefaultTarget(ContributionActionRequest $request, string $book)
    {
        $b = $this->service->setDefaultTarget($this->service->book($book), (string) $request->validated('target_amount'));

        return response()->json(['default_target' => $b->default_target]);
    }

    public function import(ContributionActionRequest $request, string $book)
    {
        $v = $request->validated();

        return response()->json($this->service->importOpening($this->service->book($book), $v['rows'], $v['as_of'], $request->user()->id, (bool) ($v['dry_run'] ?? false)));
    }

    /** All of one flat owner's ledgers (JSON for the screen), one per book. */
    public function flatLedgers(Flat $flat)
    {
        return response()->json($this->service->flatLedgers($flat));
    }

    public function ledgerPdf(Flat $flat)
    {
        return $this->ledgerPdfResponse($this->service->flatLedgers($flat), "ledger-{$flat->flat_no}.pdf");
    }

    /** One book's ledger for every flat in one PDF, one page each - to hand out or file. */
    public function allLedgersPdf(string $book)
    {
        @set_time_limit(180);

        return $this->ledgerPdfResponse($this->service->allLedgers($this->service->book($book)), "ledgers-{$book}.pdf");
    }

    public function pdf(string $book)
    {
        return Pdf::loadView('reports.contribution-statement', ['building' => Building::first(), 'report' => $this->service->statement($this->service->book($book))])
            ->setPaper('a4', 'landscape')->download("contributions-{$book}.pdf");
    }

    public function excel(ReportExportService $exports, string $book)
    {
        $spreadsheet = $exports->contributionStatement($this->service->statement($this->service->book($book)));

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, "contributions-{$book}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function ledgerPdfResponse(array $ledgers, string $filename)
    {
        return Pdf::loadView('reports.contribution-ledger', ['building' => Building::first(), 'ledgers' => $ledgers])
            ->setPaper('a4', 'portrait')->stream($filename);
    }
}
