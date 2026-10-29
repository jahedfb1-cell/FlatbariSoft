<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add or edit a bank account. On an edit (PUT) every field is optional and the opening balance cannot be
 * changed - the balance is derived from the transactions, never hand-edited.
 */
class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $create = $this->isMethod('post');
        $req = $create ? 'required' : 'sometimes';
        $phone = ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]{6,20}$/'];

        $rules = [
            'bank_name' => [$req, 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'account_no' => [$req, 'string', 'max:100'],
            'account_title' => [$req, 'string', 'max:255'],
            'mandate_rule' => ['nullable', 'string', 'max:255'],
            'sms_phone' => $phone,
        ];
        foreach ([1, 2, 3] as $n) {
            $rules["holder_{$n}_name"] = ['nullable', 'string', 'max:255'];
            $rules["holder_{$n}_phone"] = $phone;
        }
        if ($create) {
            $rules['opening_balance'] = ['nullable', 'numeric'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            '*.regex' => 'ফোন নম্বর ঠিক নয়।',
            'bank_name.required' => 'ব্যাংকের নাম আবশ্যক।',
            'account_no.required' => 'হিসাব নম্বর আবশ্যক।',
            'account_title.required' => 'হিসাবের নাম আবশ্যক।',
        ];
    }
}
