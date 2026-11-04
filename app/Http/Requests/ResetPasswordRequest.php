<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** Setting a new password with the e-mailed link (email + token) or the SMS code (mobile + code). */
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required_without:mobile', 'nullable', 'email'],
            'token' => ['required_with:email', 'nullable', 'string'],
            'mobile' => ['required_without:email', 'nullable', 'string'],
            'code' => ['required_with:mobile', 'nullable', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'নতুন পাসওয়ার্ড দিন।',
            'password.confirmed' => 'দুটি পাসওয়ার্ড মিলছে না।',
            'password.min' => 'পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের হতে হবে।',
            'token.required_with' => 'রিসেট লিংকটি সঠিক নয়।',
            'code.required_with' => 'SMS-এ পাওয়া কোডটি দিন।',
        ];
    }
}
