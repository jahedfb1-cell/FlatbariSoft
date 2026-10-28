<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GasLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            // PDF only: put each voucher/bill photo after the ledger pages
            'vouchers' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['to.after_or_equal' => 'শেষ তারিখ শুরুর তারিখের আগে হতে পারে না।'];
    }
}
