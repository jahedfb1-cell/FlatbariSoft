<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['LIFT', 'SOLAR', 'SUBSTATION', 'PUMP', 'GENERATOR', 'OTHER'])],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'supplier_mobile' => ['nullable', 'string', 'max:20'],
            'warranty_expiry' => ['nullable', 'date'],
            'amc_expiry' => ['nullable', 'date'],
            'next_service_date' => ['nullable', 'date'],
        ];
    }
}
