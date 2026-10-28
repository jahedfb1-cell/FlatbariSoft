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
            // what is still owed, and what has been entered but not yet approved (so it is not collected twice)
            'due' => max(0, (float) bcsub((string) $this->total_payable, (string) $this->paid_amount, 4)),
            'pending_amount' => bcadd((string) ($this->pending_amount ?? $this->payments()->where('status', 'PENDING')->sum('amount') ?: 0), '0', 4),
            'owner' => $this->whenLoaded('flat', fn () => $this->flat->occupantNames()['owner']),
            'tenant' => $this->whenLoaded('flat', fn () => $this->flat->occupantNames()['tenant']),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
        ];
    }
}
