<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['identifier' => ['required', 'string', 'max:255']];
    }

    public function messages(): array
    {
        return ['identifier.required' => 'ইমেইল বা মোবাইল নম্বর দিন।'];
    }
}
