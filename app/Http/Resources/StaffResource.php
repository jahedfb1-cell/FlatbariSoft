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
            // the KYC columns and the papers table arrive with the database upgrade
            'kyc_updated_at' => \Illuminate\Support\Facades\Schema::hasColumn('staff', 'kyc_updated_at') ? $this->kyc_updated_at?->timezone('Asia/Dhaka')->format('Y-m-d') : null,
            'documents_count' => $this->documents_count ?? 0,
        ];
    }
}
