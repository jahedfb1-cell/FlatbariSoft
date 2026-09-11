<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mobile' => $this->mobile,
            'role' => $this->role,
            'status' => $this->status,
            'last_login' => $this->last_login,
            // Fine-grained access control lives in spatie — the frontend
            // uses these to decide what to render, never the 'role' column.
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
        ];
    }
}
