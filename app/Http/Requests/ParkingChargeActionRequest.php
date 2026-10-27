<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One request class for the small parking actions, switched on the route:
 *  release   -> to_date
 *  generate  -> year, month
 *  pay       -> amount, method, paid_on, reference_no
 */
class ParkingChargeActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'release' => ['to_date' => ['required', 'date']],
            'generate' => [
                'year' => ['required', 'integer', 'min:2000', 'max:2100'],
                'month' => ['required', 'integer', 'min:1', 'max:12'],
            ],
            default => [
                'amount' => ['required', 'numeric', 'min:0.01'],
                'method' => ['required', Rule::in(['CASH', 'BANK', 'BKASH', 'NAGAD'])],
                'paid_on' => ['required', 'date'],
                'reference_no' => ['nullable', 'string', 'max:100'],
            ],
        };
    }

    public function messages(): array
    {
        return [
            'to_date.required' => 'তারিখ আবশ্যক।',
            'year.required' => 'বছর আবশ্যক।',
            'month.required' => 'মাস আবশ্যক।',
            'amount.required' => 'পরিমাণ আবশ্যক।',
            'amount.min' => 'পরিমাণ শূন্যের বেশি হতে হবে।',
            'paid_on.required' => 'তারিখ আবশ্যক।',
        ];
    }
}
