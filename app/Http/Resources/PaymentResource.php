<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flat_id' => $this->flat_id,
            'flat_no' => $this->whenLoaded('flat', fn () => $this->flat->flat_no),
            'owner' => $this->whenLoaded('flat', fn () => $this->flat->occupantNames()['owner']),
            'tenant' => $this->whenLoaded('flat', fn () => $this->flat->occupantNames()['tenant']),
            'bill_id' => $this->bill_id,
            'amount' => $this->amount,
            'payment_date' => $this->payment_date?->toDateString(),
            'method' => $this->method,
            'reference_no' => $this->reference_no,
            'receipt_no' => $this->receipt_no,
            'status' => $this->status,
        ];
    }
}
