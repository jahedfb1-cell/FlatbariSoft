<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFlatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => ['required', 'exists:buildings,id'],
            'flat_no' => [
                'required', 'string', 'max:20',
                Rule::unique('flats')->where('building_id', $this->input('building_id')),
            ],
            'floor' => ['required', 'string', 'max:20'],
            'size_sqft' => ['nullable', 'numeric', 'min:0'],
            'occupancy_status' => ['nullable', Rule::in(['OWNER_OCCUPIED', 'TENANTED', 'EMPTY'])],
            'service_charge_rate' => ['nullable', 'numeric', 'min:0'],
            'empty_flat_rate' => ['nullable', 'numeric', 'min:0'],
            'gas_meter_no' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }

    public function messages(): array
    {
        return [
            'building_id.required' => 'ভবন নির্বাচন আবশ্যক।',
            'flat_no.required' => 'ফ্ল্যাট নম্বর আবশ্যক।',
            'flat_no.unique' => 'এই ভবনে এই ফ্ল্যাট নম্বর ইতিমধ্যে আছে।',
            'floor.required' => 'ফ্লোর আবশ্যক।',
        ];
    }
}
