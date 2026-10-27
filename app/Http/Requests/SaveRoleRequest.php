<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Create a custom role (name + permissions) or replace a role's permissions. */
class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'string', 'regex:/^[a-z][a-z0-9_]{2,49}$/'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'রোলের নাম আবশ্যক।',
            'name.regex' => 'নামে শুধু ইংরেজি ছোট হাতের অক্ষর, সংখ্যা ও _ ব্যবহার করুন (যেমন: parking_guard)।',
        ];
    }
}
