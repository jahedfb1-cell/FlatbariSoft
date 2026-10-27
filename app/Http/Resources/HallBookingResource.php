<?php

namespace App\Http\Resources;

use App\Services\HallBookingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HallBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rentPaid = $this->netPaid('RENT');
        $depositHeld = $this->netPaid('DEPOSIT');
        $open = in_array($this->status, ['PENDING', 'CONFIRMED'], true);

        return [
            'id' => $this->id,
            'booking_no' => $this->booking_no,
            'flat_id' => $this->flat_id,
            'flat_no' => $this->whenLoaded('flat', fn () => $this->flat?->flat_no),
            'customer_name' => $this->customer_name,
            'customer_mobile' => $this->customer_mobile,
            'event_type' => $this->event_type,
            'guest_count' => $this->guest_count,
            'booking_date' => $this->booking_date?->toDateString(),
            'slot' => $this->slot,
            'start_time' => substr((string) $this->start_time, 0, 5),
            'end_time' => substr((string) $this->end_time, 0, 5),
            'rent_amount' => $this->rent_amount,
            'extra_charge' => $this->extra_charge,
            'discount' => $this->discount,
            'total_amount' => $this->total_amount,
            'security_deposit' => $this->security_deposit,
            'rent_paid' => $rentPaid,
            'rent_due' => bcsub((string) $this->total_amount, $rentPaid, 4),
            'deposit_held' => $depositHeld,
            'deposit_due' => in_array($this->status, ['CONFIRMED', 'COMPLETED'], true)
                ? bcsub((string) $this->security_deposit, $depositHeld, 4) : '0.0000',
            // what a cancellation today would give back, so the office can show it before confirming
            'policy_refund' => $open ? app(HallBookingService::class)->policyRefund($this->resource, $rentPaid) : null,
            'status' => $this->status,
            'status_reason' => $this->status_reason,
            'notes' => $this->notes,
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'type' => $p->type,
                'amount' => $p->amount,
                'method' => $p->method,
                'paid_on' => $p->paid_on?->toDateString(),
                'reference_no' => $p->reference_no,
                'receipt_no' => $p->receipt_no,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
