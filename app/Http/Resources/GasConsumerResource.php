<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GasConsumerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestReading = $this->readings()->latest('id')->first();

        return [
            'id' => $this->id,
            'flat_id' => $this->flat_id,
            'flat_no' => $this->flat?->flat_no,
            'name' => $this->name,
            'meter_no' => $this->meter_no,
            'is_internal' => $this->is_internal,
            'active' => $this->active,
            'last_reading' => $latestReading?->current_reading,
        ];
    }
}
