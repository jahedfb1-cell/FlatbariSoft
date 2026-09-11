<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'account_no' => ['required', 'string', 'max:100'],
            'account_title' => ['required', 'string', 'max:255'],
            'mandate_rule' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric'],
        ];
    }
}
