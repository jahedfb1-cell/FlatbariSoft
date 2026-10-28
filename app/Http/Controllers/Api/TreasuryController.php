<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FundStatementRequest;
use App\Http\Requests\TreasuryActionRequest;
use App\Models\Building;
use App\Models\TreasuryEntry;
use App\Services\FundStatementService;
use App\Services\ReportExportService;
use App\Services\TreasuryService;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Monthly fund statements, and the treasury entries (opening balance, bank, loans) that complete them. */
class TreasuryController extends Controller
{
    public function __construct(
        private readonly TreasuryService $treasury,
        private readonly FundStatementService $statements,
    ) {}

    // ---- reports

    public function statement(FundStatementRequest $request)
    {
        $v = $request->validated();

        return response()->json($this->statements->statement($v['fund'] ?? 'OPERATING', (int) $v['year'], (int) $v['month']));
    }

    public function summary(FundStatementRequest $request)
    {
        $v = $request->validated();

        return response()->json($this->statements->summary((int) $v['year'], (int) $v['month']));
    }

    public function pdf(FundStatementRequest $request)
    {
        $v = $request->validated();
        $report = $this->statements->statement($v['fund'] ?? 'OPERATING', (int) $v['year'], (int) $v['month']);

        return Pdf::loadView('reports.fund-statement', ['building' => Building::first(), 'report' => $report])
            ->setPaper('a4', 'portrait')->stream("statement-{$report['fund']['code']}-{$v['year']}-{$v['month']}.pdf");
    }

    public function excel(FundStatementRequest $request, ReportExportService $exports)
    {
        $v = $request->validated();
        $spreadsheet = $exports->fundStatement($this->statements->statement($v['fund'] ?? 'OPERATING', (int) $v['year'], (int) $v['month']));

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, "statement-{$v['year']}-{$v['month']}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ---- entries

    /** Newest first, with which ones have been reversed. */
    public function entries()
    {
        $reversed = TreasuryEntry::whereNotNull('reverses_id')->pluck('reverses_id')->all();

        return response()->json(TreasuryEntry::orderByDesc('entry_date')->orderByDesc('id')->limit(200)->get()->map(fn (TreasuryEntry $e) => [
            'id' => $e->id,
            'receipt_no' => $e->receipt_no,
            'kind' => $e->kind,
            'fund' => $e->fund,
            'to_fund' => $e->to_fund,
            'medium' => $e->medium,
            'amount' => $e->amount,
            'date' => $e->entry_date->toDateString(),
            'notes' => $e->notes,
            'is_reversal' => $e->reverses_id !== null,
            'is_reversed' => in_array($e->id, $reversed, true),
        ])->values());
    }

    public function openingBalance(TreasuryActionRequest $request)
    {
        $v = $request->validated();
        $this->treasury->openingBalance($v['fund'], (string) $v['cash'], (string) $v['bank'], $v['date'], $v['notes'] ?? null, $request->user()->id);

        return response()->json(['ok' => true], 201);
    }

    public function deposit(TreasuryActionRequest $request)
    {
        $v = $request->validated();
        $this->treasury->depositToBank($v['fund'], (string) $v['amount'], $v['date'], $v['notes'] ?? null, $request->user()->id);

        return response()->json(['ok' => true], 201);
    }

    public function withdraw(TreasuryActionRequest $request)
    {
        $v = $request->validated();
        $this->treasury->withdrawFromBank($v['fund'], (string) $v['amount'], $v['date'], $v['notes'] ?? null, $request->user()->id);

        return response()->json(['ok' => true], 201);
    }

    public function profit(TreasuryActionRequest $request)
    {
        $v = $request->validated();
        $this->treasury->bankProfit($v['fund'], (string) $v['amount'], $v['date'], $v['notes'] ?? null, $request->user()->id);

        return response()->json(['ok' => true], 201);
    }

    public function lend(TreasuryActionRequest $request)
    {
        $v = $request->validated();
        $this->treasury->lend($v['from_fund'], $v['to_fund'], $v['medium'], (string) $v['amount'], $v['date'], $v['notes'] ?? null, $request->user()->id);

        return response()->json(['ok' => true], 201);
    }

    /** from_fund is the borrower paying back, to_fund the lender receiving. */
    public function repay(TreasuryActionRequest $request)
    {
        $v = $request->validated();
        $this->treasury->repay($v['from_fund'], $v['to_fund'], $v['medium'], (string) $v['amount'], $v['date'], $v['notes'] ?? null, $request->user()->id);

        return response()->json(['ok' => true], 201);
    }

    public function reverse(TreasuryActionRequest $request, TreasuryEntry $entry)
    {
        $this->treasury->reverse($entry, $request->validated('reason'), $request->user()->id);

        return response()->json(['ok' => true]);
    }
}
