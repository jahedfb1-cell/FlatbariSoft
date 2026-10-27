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
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'status' => ['sometimes', Rule::in(['ACTIVE', 'INACTIVE'])],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return ['email.unique' => 'এই ইমেইল আগে থেকেই ব্যবহৃত।'];
    }
}
