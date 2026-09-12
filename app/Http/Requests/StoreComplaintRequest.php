<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'flat_id' => ['required', 'exists:flats,id'],
            'category' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['nullable', Rule::in(['LOW', 'MEDIUM', 'HIGH'])],
        ];
    }

    public function messages(): array
    {
        return [
            'flat_id.required' => 'ফ্ল্যাট নির্বাচন আবশ্যক।',
            'category.required' => 'অভিযোগের ধরন আবশ্যক।',
            'description.required' => 'বিবরণ আবশ্যক।',
        ];
    }
}
