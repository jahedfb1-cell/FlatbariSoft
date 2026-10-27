<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['sometimes', 'required', 'string'],
            'total_floors' => ['sometimes', 'required', 'integer', 'min:1'],
            'total_flats' => ['sometimes', 'required', 'integer', 'min:1'],
            'logo' => ['nullable', 'string'],
        ];
    }
}
