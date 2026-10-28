<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'flat_no' => $this->flat_no,
            'floor' => $this->floor,
            'size_sqft' => $this->size_sqft,
            'occupancy_status' => $this->occupancy_status,
            'service_charge_rate' => $this->service_charge_rate,
            'empty_flat_rate' => $this->empty_flat_rate,
            'gas_meter_no' => $this->gas_meter_no,
            'status' => $this->status,
            'owner_name' => $this->whenLoaded('currentOwners', fn () => $this->occupantNames()['owner']),
            'tenant_name' => $this->whenLoaded('currentTenant', fn () => $this->occupantNames()['tenant']),
            'current_owners' => FlatPartyResource::collection($this->whenLoaded('currentOwners')),
            'current_tenant' => TenantResource::collection($this->whenLoaded('currentTenant')),
        ];
    }
}
