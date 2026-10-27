<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flat_id' => $this->flat_id,
            'flat_no' => $this->whenLoaded('flat', fn () => $this->flat->flat_no),
            'service_charge' => $this->service_charge,
            'gas_amount' => $this->gas_amount,
            'other_charges' => $this->other_charges,
            'late_fee' => $this->late_fee,
            'previous_due' => $this->previous_due,
            'total_payable' => $this->total_payable,
            'paid_amount' => $this->paid_amount,
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
        ];
    }
}
