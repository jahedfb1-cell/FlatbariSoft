<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // either pick someone already in the system, or give a name + mobile and one is created
            'person_id' => ['nullable', 'integer', 'exists:persons,id'],
            'name_bn' => ['required_without:person_id', 'nullable', 'string', 'max:255'],
            'mobile' => ['required_without:person_id', 'nullable', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name_bn.required_without' => 'নাম আবশ্যক।',
            'mobile.required_without' => 'মোবাইল নম্বর আবশ্যক।',
            'mobile.regex' => 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন।',
            'email.unique' => 'এই ইমেইল আগে থেকেই ব্যবহৃত।',
            'password.required' => 'পাসওয়ার্ড আবশ্যক।',
            'password.min' => 'পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের হতে হবে।',
            'roles.required' => 'কমপক্ষে একটি রোল দিন।',
        ];
    }
}
