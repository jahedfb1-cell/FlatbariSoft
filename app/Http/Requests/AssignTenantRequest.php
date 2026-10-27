<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'person.name_bn' => ['required', 'string', 'max:255'],
            'person.name_en' => ['nullable', 'string', 'max:255'],
            'person.mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'person.nid' => ['nullable', 'string', 'max:30'],
            'person.email' => ['nullable', 'email', 'max:255'],
            'person.address' => ['nullable', 'string'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'family_members_count' => ['nullable', 'integer', 'min:0', 'max:50'],
            'start_date' => ['required', 'date'],
            'monthly_rent' => ['nullable', 'numeric', 'min:0'],
            'security_deposit' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'person.name_bn.required' => 'ভাড়াটিয়ার নাম (বাংলা) আবশ্যক।',
            'person.mobile.required' => 'ভাড়াটিয়ার মোবাইল নম্বর আবশ্যক।',
            'person.mobile.regex' => 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন।',
            'start_date.required' => 'ভাড়া শুরুর তারিখ আবশ্যক।',
        ];
    }
}
