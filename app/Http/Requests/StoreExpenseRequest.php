<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'expense_head_id' => ['required', 'exists:expense_heads,id'],
            'payee' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
            'treasury_medium' => ['required', Rule::in(['BANK', 'CASH'])],
            'fund_type' => ['nullable', Rule::in(['OPERATING', 'GAS', 'CAPITAL', 'SINKING'])],
            'resolution_id' => ['nullable', 'integer'],
        ];
    }
}
