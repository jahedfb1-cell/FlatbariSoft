<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'designation' => ['sometimes', 'required', 'string', 'max:100'],
            'mobile' => ['sometimes', 'required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'nid' => ['nullable', 'string', 'max:30'],
            'join_date' => ['sometimes', 'required', 'date'],
            'monthly_salary' => ['sometimes', 'required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন।',
        ];
    }
}
