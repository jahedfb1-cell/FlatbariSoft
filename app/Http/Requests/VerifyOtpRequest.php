<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $length = config('otp.length');

        return [
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'code' => ['required', 'digits:'.$length],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'মোবাইল নম্বর আবশ্যক।',
            'mobile.regex' => 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন (যেমন 01712345678)।',
            'code.required' => 'OTP কোড আবশ্যক।',
            'code.digits' => 'OTP কোড '.config('otp.length').' সংখ্যার হতে হবে।',
        ];
    }
}
