<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'purchase_cost' => $this->purchase_cost,
            'supplier_name' => $this->supplier_name,
            'warranty_expiry' => $this->warranty_expiry?->toDateString(),
            'amc_expiry' => $this->amc_expiry?->toDateString(),
            'next_service_date' => $this->next_service_date?->toDateString(),
            'service_overdue' => $this->isServiceOverdue(),
            'status' => $this->status,
        ];
    }
}
