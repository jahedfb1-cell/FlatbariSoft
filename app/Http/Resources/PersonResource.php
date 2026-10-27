<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_bn' => $this->name_bn,
            'name_en' => $this->name_en,
            'country_code' => $this->country_code,
            'mobile' => $this->mobile,
            'alt_mobile' => $this->alt_mobile,
            'nid' => $this->nid,
            'email' => $this->email,
            'address' => $this->address,
            'photo' => $this->photo,
            'preferred_channel' => $this->preferred_channel,
        ];
    }
}
