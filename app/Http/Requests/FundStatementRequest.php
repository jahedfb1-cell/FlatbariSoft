<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FundStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fund' => ['sometimes', Rule::in(['OPERATING', 'GAS', 'CAPITAL', 'SINKING'])],
            // the combined statement: which funds go into the one document (default: service charge + gas)
            'funds' => ['sometimes', 'array', 'min:1', 'max:4'],
            'funds.*' => [Rule::in(['OPERATING', 'GAS', 'CAPITAL', 'SINKING']), 'distinct'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ];
    }
}
