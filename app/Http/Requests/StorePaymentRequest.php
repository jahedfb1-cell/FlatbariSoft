<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'flat_id' => ['required', 'exists:flats,id'],
            'bill_id' => ['nullable', 'exists:bills,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'method' => ['required', Rule::in(['CASH', 'BANK', 'BKASH', 'NAGAD'])],
            'reference_no' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'flat_id.required' => 'ফ্ল্যাট নির্বাচন আবশ্যক।',
            'amount.required' => 'পরিমাণ আবশ্যক।',
            'amount.min' => 'পরিমাণ শূন্যের বেশি হতে হবে।',
        ];
    }
}
