<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create or edit a slot: the code must stay unique. */
class StoreParkingSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $slot = $this->route('slot');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('parking_slots', 'code')->ignore($slot)->whereNull('deleted_at')],
            'zone' => ['nullable', 'string', 'max:60'],
            'vehicle_type' => ['required', Rule::in(['CAR', 'MOTORBIKE', 'BICYCLE'])],
            'monthly_fee' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'স্লট নম্বর আবশ্যক।',
            'code.unique' => 'এই নম্বরের স্লট আগে থেকেই আছে।',
            'monthly_fee.required' => 'মাসিক চার্জ আবশ্যক (ফ্রি হলে ০)।',
        ];
    }
}
