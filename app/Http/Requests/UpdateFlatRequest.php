<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFlatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $flat = $this->route('flat');

        return [
            'flat_no' => [
                'sometimes', 'required', 'string', 'max:20',
                Rule::unique('flats')
                    ->where('building_id', $this->input('building_id', $flat->building_id))
                    ->ignore($flat->id),
            ],
            'floor' => ['sometimes', 'required', 'string', 'max:20'],
            'size_sqft' => ['nullable', 'numeric', 'min:0'],
            'occupancy_status' => ['sometimes', Rule::in(['OWNER_OCCUPIED', 'TENANTED', 'EMPTY'])],
            'service_charge_rate' => ['sometimes', 'numeric', 'min:0'],
            'empty_flat_rate' => ['sometimes', 'numeric', 'min:0'],
            'gas_meter_no' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }

    public function messages(): array
    {
        return [
            'flat_no.unique' => 'এই ভবনে এই ফ্ল্যাট নম্বর ইতিমধ্যে আছে।',
        ];
    }
}
