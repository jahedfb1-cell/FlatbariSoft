<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GasLedgerRequest;
use App\Services\GasLedgerService;
use App\Services\InvoicePrintService;
use App\Services\ReportExportService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GasLedgerController extends Controller
{
    public function __construct(
        private readonly GasLedgerService $ledger,
        private readonly InvoicePrintService $printer,
        private readonly ReportExportService $exports,
    ) {}

    public function index(GasLedgerRequest $request)
    {
        return response()->json($this->ledger->ledger($request->validated('from'), $request->validated('to')));
    }

    /** The ledger as a black-and-white A4 PDF; with vouchers=1 every bill photo follows, one per page. */
    public function pdf(GasLedgerRequest $request)
    {
        $v = $request->validated();
        $data = $this->ledger->ledger($v['from'] ?? null, $v['to'] ?? null);
        $pdf = $this->printer->gasLedger($data, (bool) ($v['vouchers'] ?? false), $this->ledger->purchases($v['from'] ?? null, $v['to'] ?? null));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="gas-purchase-ledger.pdf"',
        ]);
    }

    public function excel(GasLedgerRequest $request)
    {
        $data = $this->ledger->ledger($request->validated('from'), $request->validated('to'));
        $spreadsheet = $this->exports->gasLedger($data);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'gas-purchase-ledger.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
