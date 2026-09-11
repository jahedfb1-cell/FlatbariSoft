<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Bangladeshi mobile: 01 + operator digit (3-9) + 8 digits = 11 digits.
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'মোবাইল নম্বর আবশ্যক।',
            'mobile.regex' => 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন (যেমন 01712345678)।',
        ];
    }
}
