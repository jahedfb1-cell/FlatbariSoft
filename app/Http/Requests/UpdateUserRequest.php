<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_bn' => ['sometimes', 'required', 'string', 'max:255'],
            'mobile' => ['sometimes', 'required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'status' => ['sometimes', Rule::in(['ACTIVE', 'INACTIVE'])],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'এই ইমেইল আগে থেকেই ব্যবহৃত।',
            'name_bn.required' => 'নাম আবশ্যক।',
            'mobile.required' => 'মোবাইল নম্বর আবশ্যক।',
            'mobile.regex' => 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন।',
        ];
    }
}
