<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseInvoiceRequest;
use App\Http\Resources\ConstructionMaterialPurchaseResource;
use App\Http\Resources\GasPurchaseResource;
use App\Models\ConstructionMaterialPurchase;
use App\Models\GasPurchase;
use App\Services\InvoiceImageService;
use App\Services\InvoicePrintService;
use Illuminate\Database\Eloquent\Model;

/**
 * The invoice photo of a gas purchase ('gas') or a construction material
 * purchase ('materials'). One controller for both because every operation is
 * identical; routes/api.php registers each type under its own URL prefix with
 * its own permission, passing the type in as a route default.
 *
 * Laravel hands route parameters to a controller POSITIONALLY, URL segments
 * first and `defaults()` after - so every action takes ($purchase, $type) in
 * that order, never the other way round (it fails with a TypeError).
 */
class PurchaseInvoiceController extends Controller
{
    private const MODELS = [
        'gas' => GasPurchase::class,
        'materials' => ConstructionMaterialPurchase::class,
    ];

    private const FOLDERS = [
        'gas' => 'invoices/gas',
        'materials' => 'invoices/materials',
    ];

    public function __construct(
        private readonly InvoiceImageService $images,
        private readonly InvoicePrintService $printer,
    ) {}

    /** The stored (compressed, colour) photo itself — never a public URL. */
    public function show(int $purchase, string $type)
    {
        $model = $this->find($type, $purchase);
        abort_unless($this->images->exists($model->invoice_file), 404, 'এই ক্রয়ের সাথে কোনো ইনভয়েসের ছবি নেই।');

        return $this->images->response($model->invoice_file);
    }

    /** Attach the photo, or replace the existing one (the old file is deleted). */
    public function store(StorePurchaseInvoiceRequest $request, int $purchase, string $type)
    {
        $model = $this->find($type, $purchase);

        $stored = $this->images->store($request->file('invoice_image'), self::FOLDERS[$type]);
        $old = $model->invoice_file;
        $model->update(['invoice_file' => $stored['path']]);
        $this->images->delete($old);

        $resource = $type === 'gas' ? GasPurchaseResource::class : ConstructionMaterialPurchaseResource::class;

        return new $resource($model->fresh('expense'));
    }

    /** One A4 page, black and white. */
    public function print(int $purchase, string $type)
    {
        $model = $this->find($type, $purchase);
        abort_unless($this->images->exists($model->invoice_file), 404, 'এই ক্রয়ের সাথে কোনো ইনভয়েসের ছবি নেই।');

        return response($this->printer->single($type, $model), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"invoice-{$type}-{$model->id}.pdf\"",
        ]);
    }

    private function find(string $type, int $id): Model
    {
        return self::MODELS[$type]::with('expense')->findOrFail($id);
    }
}
