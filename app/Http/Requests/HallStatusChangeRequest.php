<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Reject / cancel: a reason is always recorded; cancel may also override the refund. */
class HallStatusChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'method' => ['nullable', Rule::in(['CASH', 'BANK', 'BKASH', 'NAGAD'])],
        ];
    }

    public function messages(): array
    {
        return ['reason.required' => 'কারণ লিখুন।'];
    }
}
