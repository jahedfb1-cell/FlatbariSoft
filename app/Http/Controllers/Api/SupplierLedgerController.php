<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierLedgerRequest;
use App\Services\InvoicePrintService;
use App\Services\ReportExportService;
use App\Services\SupplierLedgerService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Supplier accounts for gas and for building construction material. The route default `kind` picks which one;
 * only the permission differs (gas.view / expense.view).
 */
class SupplierLedgerController extends Controller
{
    public function __construct(
        private readonly SupplierLedgerService $suppliers,
        private readonly InvoicePrintService $printer,
        private readonly ReportExportService $exports,
    ) {}

    public function index(SupplierLedgerRequest $request)
    {
        $v = $request->validated();

        return response()->json($this->suppliers->suppliers($v['kind'], $v['from'] ?? null, $v['to'] ?? null));
    }

    public function ledger(SupplierLedgerRequest $request)
    {
        return response()->json($this->data($request));
    }

    public function pdf(SupplierLedgerRequest $request)
    {
        return response($this->printer->supplierLedger($this->data($request)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="supplier-ledger.pdf"',
        ]);
    }

    public function excel(SupplierLedgerRequest $request)
    {
        $spreadsheet = $this->exports->supplierLedger($this->data($request));

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'supplier-ledger.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function data(SupplierLedgerRequest $request): array
    {
        $v = $request->validated();
        abort_unless(isset($v['supplier']), 422, 'supplier লাগবে।');

        return $this->suppliers->ledger($v['kind'], $v['supplier'], $v['from'] ?? null, $v['to'] ?? null);
    }
}
