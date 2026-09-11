<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['required', 'string', 'max:100'],
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'nid' => ['nullable', 'string', 'max:30'],
            'join_date' => ['required', 'date'],
            'monthly_salary' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'নাম আবশ্যক।',
            'designation.required' => 'পদবি আবশ্যক।',
            'mobile.required' => 'মোবাইল নম্বর আবশ্যক।',
            'mobile.regex' => 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন।',
            'join_date.required' => 'যোগদানের তারিখ আবশ্যক।',
            'monthly_salary.required' => 'মাসিক বেতন আবশ্যক।',
        ];
    }
}
