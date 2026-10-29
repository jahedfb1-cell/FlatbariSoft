<?php

namespace App\Http\Requests;

use App\Models\FlatParty;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * A resident telling the office "I paid this by bKash/Nagad/bank, here is the transaction id". It becomes a
 * PENDING payment that the office checks against its own statement and approves; nothing is credited until then.
 * The flat must be one of the resident's own, the bill one of that flat's, and the same transaction id cannot be
 * submitted twice.
 */
class StoreMyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $personId = Auth::user()->person_id;
        $flatIds = $personId ? FlatParty::where('person_id', $personId)->whereNull('to_date')->pluck('flat_id')->all() : [];

        return [
            'flat_id' => ['required', Rule::in($flatIds)],
            'bill_id' => ['nullable', Rule::exists('bills', 'id')->where('flat_id', $this->input('flat_id'))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'method' => ['required', Rule::in(['BKASH', 'NAGAD', 'BANK'])],
            'reference_no' => ['required', 'string', 'max:100'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($this->filled('reference_no') && Payment::where('method', $this->input('method'))->where('reference_no', trim((string) $this->input('reference_no')))->exists()) {
                $validator->errors()->add('reference_no', 'এই ট্রানজেকশন আইডি দিয়ে আগেই পেমেন্ট জমা দেওয়া হয়েছে।');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'flat_id.in' => 'এটি আপনার ফ্ল্যাট নয়।',
            'reference_no.required' => 'ট্রানজেকশন আইডি দিন।',
            'amount.gt' => 'পরিমাণ শূন্যের বেশি হতে হবে।',
        ];
    }
}
