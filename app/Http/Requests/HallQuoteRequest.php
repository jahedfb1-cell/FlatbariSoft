<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HallQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slot' => ['required', Rule::in(['DAY', 'NIGHT', 'FULL_DAY'])],
            // true = resident rate, false = outsider rate (the portal always asks for the resident rate)
            'resident' => ['nullable', 'boolean'],
        ];
    }
}
