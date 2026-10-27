<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AllocateParkingSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'flat_id' => ['required', 'integer', 'exists:flats,id'],
            'person_id' => ['nullable', 'integer', 'exists:persons,id'],
            'vehicle_no' => ['nullable', 'string', 'max:40'],
            'from_date' => ['required', 'date'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'flat_id.required' => 'ফ্ল্যাট নির্বাচন আবশ্যক।',
            'from_date.required' => 'শুরুর তারিখ আবশ্যক।',
        ];
    }
}
