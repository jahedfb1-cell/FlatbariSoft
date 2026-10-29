<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** An approval always carries the approver's written note - what they checked and why they agree. */
class ApproveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:3', 'max:1000']];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'অনুমোদনের সাথে কিছু লিখুন — কী দেখে অনুমোদন দিচ্ছেন।',
            'note.min' => 'মন্তব্য অন্তত ৩ অক্ষরের হতে হবে।',
        ];
    }
}
