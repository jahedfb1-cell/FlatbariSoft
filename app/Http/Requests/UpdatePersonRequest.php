<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_bn' => ['sometimes', 'required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:5'],
            'mobile' => ['sometimes', 'required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'alt_mobile' => ['nullable', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'nid' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'preferred_channel' => ['nullable', Rule::in(['SMS', 'WHATSAPP', 'EMAIL', 'APP'])],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন।',
            'alt_mobile.regex' => 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন।',
        ];
    }
}
