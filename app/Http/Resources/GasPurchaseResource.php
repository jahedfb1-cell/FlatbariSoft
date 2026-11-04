<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GasPurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'supplier_name' => $this->supplier_name,
            'supplier_id' => $this->supplier_id ?? null,
            'cylinders' => $this->cylinders ?? null,
            'cylinder_kg' => $this->cylinder_kg ?? null,
            'price_per_cylinder' => $this->price_per_cylinder ?? null,
            'quantity_kg' => $this->quantity_kg,
            'unit_price' => $this->unit_price,
            'transport_cost' => $this->transport_cost,
            'total_cost' => $this->total_cost,
            'has_invoice' => (bool) $this->invoice_file,
            'invoice_kb' => $this->invoice_file ? app(\App\Services\InvoiceImageService::class)->sizeKb($this->invoice_file) : null,
            'expense_status' => $this->whenLoaded('expense', fn () => $this->expense->status),
        ];
    }
}
