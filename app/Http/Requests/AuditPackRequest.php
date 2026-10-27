<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditPackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in(['all', 'gas', 'materials'])],
        ];
    }

    public function messages(): array
    {
        return [
            'from.required' => 'শুরুর তারিখ আবশ্যক।',
            'to.required' => 'শেষ তারিখ আবশ্যক।',
            'to.after_or_equal' => 'শেষ তারিখ শুরুর তারিখের আগে হতে পারে না।',
        ];
    }
}
