<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContributionActionRequest;
use App\Http\Requests\StoreContributionRequest;
use App\Models\Building;
use App\Models\Contribution;
use App\Models\ContributionHead;
use App\Models\Flat;
use App\Support\PdfName;
use App\Services\ContributionService;
use App\Services\ReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
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
        $v = $request->validated();
        if (! empty($v['lines'])) {
            $this->service->recordMany($flat, $v, $request->user()->id);
        } else {
            $this->service->record($flat, $v, $request->user()->id);
        }

        return response()->json($this->service->flatLedgers($flat), 201);
    }

    /** The printable money receipt of one collection - what the owner is handed when the money is taken. */
    public function receipt(Contribution $contribution)
    {
        return $this->receiptPdf(collect([$contribution]));
    }

    /** One receipt for a payment that covered several heads: ?ids[]=12&ids[]=13 (all of one flat, one book). */
    public function receipts(Request $request)
    {
        $ids = collect((array) $request->query('ids'))->map(fn ($i) => (int) $i)->filter()->unique()->values();
        $rows = Contribution::whereIn('id', $ids)->orderBy('id')->get();
        if ($ids->isEmpty() || $rows->count() !== $ids->count()) {
            throw ValidationException::withMessages(['ids' => 'রসিদ পাওয়া যায়নি।']);
        }

        return $this->receiptPdf($rows);
    }

    private function receiptPdf(Collection $rows)
    {
        $rows->each->loadMissing('flat', 'head.book');
        if ($rows->contains(fn ($c) => $c->method === 'OPENING' || $c->reverses_id !== null)) {
            throw ValidationException::withMessages(['receipt' => 'শুরুর জমা বা বাতিলের এন্ট্রির রসিদ হয় না।']);
        }
        if ($rows->pluck('flat_id')->unique()->count() > 1 || $rows->map(fn ($c) => $c->head->contribution_book_id)->unique()->count() > 1) {
            throw ValidationException::withMessages(['receipt' => 'এক রসিদে একই ফ্ল্যাটের একই খাতার জমা থাকতে হবে।']);
        }
        $first = $rows->first();
        $ledger = $this->service->ledger($first->flat, $first->head->book);
        $entries = $rows->map(fn ($c) => collect($ledger['entries'])->firstWhere('id', $c->id))->values()->all();

        // flat no-owner name-what it was paid for, e.g. 10A-Nazrul Islam-Subscription + Gas line
        $name = PdfName::forOwner($first->flat, $rows->map(fn ($c) => $c->head->name_en)->unique()->implode(' + '));

        return PdfName::titled(Pdf::loadView('reports.contribution-receipt', [
            'building' => Building::first(),
            'book' => ['name_en' => $first->head->book->name_en],
            'ledger' => $ledger,
            'entries' => $entries,
        ])->setPaper('a5', 'landscape'), $name)->stream($name);
    }

    /** Every head of a book - including those switched off - with whether money was ever collected under it. */
    public function heads(string $book)
    {
        $b = $this->service->book($book);
        $used = Contribution::select('contribution_head_id')->distinct()->pluck('contribution_head_id')->flip();

        return response()->json(ContributionHead::where('contribution_book_id', $b->id)->orderBy('sort_order')->orderBy('id')->get()->map(fn ($h) => [
            'id' => $h->id, 'code' => $h->code, 'name_bn' => $h->name_bn, 'name_en' => $h->name_en,
            'counts_toward_target' => $h->counts_toward_target, 'is_active' => $h->is_active, 'used' => $used->has($h->id),
        ])->values());
    }

    public function storeHead(ContributionActionRequest $request, string $book)
    {
        $this->service->createHead($this->service->book($book), $request->validated(), $request->user()->id);

        return $this->heads($book);
    }

    public function updateHead(ContributionActionRequest $request, ContributionHead $head)
    {
        $this->service->updateHead($head, $request->validated(), $request->user()->id);

        return $this->heads($head->book->code);
    }

    public function destroyHead(Request $request, ContributionHead $head)
    {
        $code = $head->book->code;
        $this->service->deleteHead($head, $request->user()->id);

        return $this->heads($code);
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
        return $this->ledgerPdfResponse($this->service->flatLedgers($flat), PdfName::forOwner($flat, 'Owner Collection Ledger'));
    }

    /** One book's ledger for every flat in one PDF, one page each - to hand out or file. */
    public function allLedgersPdf(string $book)
    {
        @set_time_limit(180);

        $b = $this->service->book($book);

        return $this->ledgerPdfResponse($this->service->allLedgers($b), PdfName::make($b->name_en, 'All Owner Ledgers'));
    }

    public function pdf(string $book)
    {
        $b = $this->service->book($book);
        $name = PdfName::make($b->name_en, 'Collection Statement', now('Asia/Dhaka')->format('d M Y'));

        return PdfName::titled(Pdf::loadView('reports.contribution-statement', ['building' => Building::first(), 'report' => $this->service->statement($b)])
            ->setPaper('a4', 'landscape'), $name)->download($name);
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
        return PdfName::titled(Pdf::loadView('reports.contribution-ledger', ['building' => Building::first(), 'ledgers' => $ledgers])
            ->setPaper('a4', 'portrait'), $filename)->stream($filename);
    }
}
