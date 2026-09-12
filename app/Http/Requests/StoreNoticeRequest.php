<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'audience' => ['nullable', Rule::in(['ALL', 'OWNERS', 'TENANTS'])],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'শিরোনাম আবশ্যক।',
            'body.required' => 'বিবরণ আবশ্যক।',
        ];
    }
}
