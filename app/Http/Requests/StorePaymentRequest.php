<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Payment;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Rocket and Upay (and the bank name / note) arrive with the payments database upgrade; until then only the old methods are offered
        $upgraded = Schema::hasColumn('payments', 'bank_name');
        $mobile = $upgraded ? ['BKASH', 'NAGAD', 'ROCKET', 'UPAY'] : ['BKASH', 'NAGAD'];

        return [
            'flat_id' => ['required', 'exists:flats,id'],
            'bill_id' => ['nullable', 'exists:bills,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date', 'before_or_equal:'.now('Asia/Dhaka')->toDateString()],
            'method' => ['required', Rule::in(array_merge(['CASH'], ['BANK'], $mobile))],
            // a cheque / transaction number is what lets the books be matched to the bank or the wallet statement
            'reference_no' => [Rule::requiredIf(fn () => $this->input('method') !== 'CASH'), 'nullable', 'string', 'max:100'],
            'bank_name' => [Rule::requiredIf(fn () => $upgraded && $this->input('method') === 'BANK'), 'nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'flat_id.required' => 'ফ্ল্যাট নির্বাচন আবশ্যক।',
            'amount.required' => 'পরিমাণ আবশ্যক।',
            'amount.min' => 'পরিমাণ শূন্যের বেশি হতে হবে।',
            'reference_no.required' => 'চেক / ট্রানজেকশন নম্বর আবশ্যক।',
            'bank_name.required' => 'ব্যাংকের নাম আবশ্যক।',
            'payment_date.before_or_equal' => 'ভবিষ্যতের তারিখে পেমেন্ট এন্ট্রি করা যাবে না।',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $ref = trim((string) $this->input('reference_no'));
            if ($ref !== '' && $this->input('method') !== 'CASH' && Payment::where('method', $this->input('method'))->where('reference_no', $ref)->exists()) {
                $v->errors()->add('reference_no', 'এই রেফারেন্স / ট্রানজেকশন নম্বর দিয়ে আগেই একটি পেমেন্ট এন্ট্রি করা আছে।');
            }
        });
    }

}
