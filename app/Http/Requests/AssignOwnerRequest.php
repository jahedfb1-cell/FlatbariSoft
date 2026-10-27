<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignOwnerRequest extends FormRequest
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
            'from_date' => ['required', 'date'],
            'is_billing_contact' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'person.name_bn.required' => 'মালিকের নাম (বাংলা) আবশ্যক।',
            'person.mobile.required' => 'মালিকের মোবাইল নম্বর আবশ্যক।',
            'person.mobile.regex' => 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন।',
            'from_date.required' => 'মালিকানা শুরুর তারিখ আবশ্যক।',
        ];
    }
}
