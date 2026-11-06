<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditPackRequest;
use App\Support\PdfName;
use App\Services\InvoicePrintService;

class InvoiceAuditController extends Controller
{
    public function __construct(private readonly InvoicePrintService $printer) {}

    /** Every purchase invoice in a date range as one black-and-white A4 PDF, for an audit. */
    public function pack(AuditPackRequest $request)
    {
        $data = $request->validated();
        $pdf = $this->printer->auditPack($data['from'], $data['to'], $data['type'] ?? 'all');

        $name = PdfName::make('Invoice Audit Pack', PdfName::range($data['from'], $data['to']));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => PdfName::disposition($name),
        ]);
    }
}
