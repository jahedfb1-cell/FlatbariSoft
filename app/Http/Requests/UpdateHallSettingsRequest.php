<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHallSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'capacity' => ['required', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'security_deposit' => ['required', 'numeric', 'min:0'],
            'advance_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'free_cancel_days' => ['required', 'integer', 'min:0', 'max:365'],
            'late_cancel_refund_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'rates' => ['required', 'array', 'min:1'],
            'rates.*.slot' => ['required', Rule::in(['DAY', 'NIGHT', 'FULL_DAY'])],
            'rates.*.start_time' => ['required', 'date_format:H:i'],
            'rates.*.end_time' => ['required', 'date_format:H:i'],
            'rates.*.resident_rate' => ['required', 'numeric', 'min:0'],
            'rates.*.outsider_rate' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'হলের নাম আবশ্যক।',
            'rates.required' => 'ভাড়ার রেট আবশ্যক।',
        ];
    }
}
