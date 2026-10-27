<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'বর্তমান পাসওয়ার্ড আবশ্যক।',
            'new_password.required' => 'নতুন পাসওয়ার্ড আবশ্যক।',
            'new_password.min' => 'নতুন পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের হতে হবে।',
            'new_password.confirmed' => 'নতুন পাসওয়ার্ড দুটো মিলছে না।',
        ];
    }
}
