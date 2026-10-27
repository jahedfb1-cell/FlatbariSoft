<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flat_id' => $this->flat_id,
            'occupation' => $this->occupation,
            'family_members_count' => $this->family_members_count,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'monthly_rent' => $this->monthly_rent,
            'security_deposit' => $this->security_deposit,
            'status' => $this->status,
            'person' => new PersonResource($this->whenLoaded('person')),
        ];
    }
}
