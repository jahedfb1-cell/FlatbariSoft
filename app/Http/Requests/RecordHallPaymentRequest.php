<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordHallPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['RENT', 'DEPOSIT', 'DEPOSIT_REFUND'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(['CASH', 'BANK', 'BKASH', 'NAGAD'])],
            'paid_on' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'পরিমাণ আবশ্যক।',
            'amount.min' => 'পরিমাণ শূন্যের বেশি হতে হবে।',
            'paid_on.required' => 'তারিখ আবশ্যক।',
        ];
    }
}
