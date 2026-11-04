<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Password login by e-mail address or mobile number (the old "email" field still works). */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('identifier') && $this->filled('email')) {
            $this->merge(['identifier' => $this->input('email')]);
        }
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return ['identifier.required' => 'ইমেইল বা মোবাইল নম্বর দিন।', 'password.required' => 'পাসওয়ার্ড দিন।'];
    }
}
