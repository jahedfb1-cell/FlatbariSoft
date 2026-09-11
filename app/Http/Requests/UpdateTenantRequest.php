<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occupation' => ['nullable', 'string', 'max:100'],
            'family_members_count' => ['nullable', 'integer', 'min:0', 'max:50'],
            'monthly_rent' => ['nullable', 'numeric', 'min:0'],
            'security_deposit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
