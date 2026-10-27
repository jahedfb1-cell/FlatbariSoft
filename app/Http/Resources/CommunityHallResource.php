<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityHallResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'description' => $this->description,
            'security_deposit' => $this->security_deposit,
            'advance_percent' => $this->advance_percent,
            'free_cancel_days' => $this->free_cancel_days,
            'late_cancel_refund_percent' => $this->late_cancel_refund_percent,
            'is_active' => $this->is_active,
            'rates' => $this->rates->sortBy(fn ($r) => array_search($r->slot, ['DAY', 'NIGHT', 'FULL_DAY']))->map(fn ($r) => [
                'slot' => $r->slot,
                'start_time' => substr((string) $r->start_time, 0, 5),
                'end_time' => substr((string) $r->end_time, 0, 5),
                'resident_rate' => $r->resident_rate,
                'outsider_rate' => $r->outsider_rate,
            ])->values(),
        ];
    }
}
