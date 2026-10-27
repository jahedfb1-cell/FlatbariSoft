<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParkingChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paid = bcadd($this->paid(), '0', 4);
        $due = bcsub((string) $this->amount, $paid, 4);
        $allocation = $this->allocation;

        return [
            'id' => $this->id,
            'year' => $this->year,
            'month' => $this->month,
            'slot_code' => $allocation?->slot?->code,
            'vehicle_type' => $allocation?->slot?->vehicle_type,
            'flat_id' => $allocation?->flat_id,
            'flat_no' => $allocation?->flat?->flat_no,
            'vehicle_no' => $allocation?->vehicle_no,
            'amount' => $this->amount,
            'paid' => $paid,
            'due' => $due,
            'due_date' => $this->due_date?->toDateString(),
            'status' => bccomp($due, '0', 4) <= 0 ? 'PAID' : (bccomp($paid, '0', 4) > 0 ? 'PARTIAL' : 'UNPAID'),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'method' => $p->method,
                'paid_on' => $p->paid_on?->toDateString(),
                'reference_no' => $p->reference_no,
                'receipt_no' => $p->receipt_no,
            ])->values()),
        ];
    }
}
