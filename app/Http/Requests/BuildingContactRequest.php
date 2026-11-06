<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BuildingContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{5,30}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'নাম লিখুন।',
            'phone.required' => 'ফোন নম্বর লিখুন।',
            'phone.regex' => 'ফোন নম্বর ঠিক নয় (শুধু সংখ্যা, + - ও ফাঁকা)।',
        ];
    }
}
