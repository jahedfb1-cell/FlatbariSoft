<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A rejection always carries the reason - the person who entered the expense is shown it. */
class RejectExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:1000']];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'প্রত্যাখ্যানের কারণ লিখুন।',
            'reason.min' => 'কারণ অন্তত ৩ অক্ষরের হতে হবে।',
        ];
    }
}
