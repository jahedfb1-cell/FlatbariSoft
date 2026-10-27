<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'meeting_date' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'agenda' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'শিরোনাম আবশ্যক।',
            'meeting_date.required' => 'মিটিংয়ের তারিখ আবশ্যক।',
        ];
    }
}
