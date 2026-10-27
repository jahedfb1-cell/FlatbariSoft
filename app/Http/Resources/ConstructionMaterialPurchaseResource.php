<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstructionMaterialPurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'item_name' => $this->item_name,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unit_rate' => $this->unit_rate,
            'supplier_name' => $this->supplier_name,
            'transport_cost' => $this->transport_cost,
            'total_cost' => $this->total_cost,
            'has_invoice' => (bool) $this->invoice_file,
            'invoice_kb' => $this->invoice_file ? app(\App\Services\InvoiceImageService::class)->sizeKb($this->invoice_file) : null,
            'expense_status' => $this->whenLoaded('expense', fn () => $this->expense->status),
        ];
    }
}
