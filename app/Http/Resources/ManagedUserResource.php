<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A user as the user-management screen sees them (not the logged-in user's own UserResource). */
class ManagedUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->person?->name_bn ?? $this->person?->name_en,
            // the flats this person holds today (an owner or tenant login), e.g. [{flat_no: '10A', role: 'OWNER'}]
            'flats' => $this->person?->flatParties->filter(fn ($p) => $p->to_date === null && $p->flat)
                ->map(fn ($p) => ['flat_no' => $p->flat->flat_no, 'role' => $p->role])->values() ?? [],
            'mobile' => $this->mobile,
            'email' => $this->email,
            'status' => $this->status,
            'role' => $this->role,
            'roles' => $this->getRoleNames()->values(),
            'last_login' => $this->last_login?->toIso8601String(),
        ];
    }
}
