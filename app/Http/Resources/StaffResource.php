<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'designation' => $this->designation,
            'mobile' => $this->mobile,
            'nid' => $this->nid,
            'join_date' => $this->join_date?->toDateString(),
            'monthly_salary' => $this->monthly_salary,
            'photo' => $this->photo,
            'status' => $this->status,
        ];
    }
}
