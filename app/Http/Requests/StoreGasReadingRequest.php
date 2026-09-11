<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGasReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gas_consumer_id' => ['required', 'exists:gas_consumers,id'],
            'billing_period_id' => ['required', 'exists:billing_periods,id'],
            'current_reading' => ['required', 'numeric', 'min:0'],
            'reading_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_reading.required' => 'বর্তমান রিডিং আবশ্যক।',
        ];
    }
}
