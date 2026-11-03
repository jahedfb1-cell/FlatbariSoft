<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Correcting a bill's amounts: every figure plus a written reason (the change is also kept in the audit log). */
class UpdateBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_charge' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'gas_amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'other_charges' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'late_fee' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['reason.required' => 'কেন বিল সংশোধন করছেন তা লিখুন।', 'reason.min' => 'কারণ কমপক্ষে ৩ অক্ষর লিখুন।'];
    }
}
