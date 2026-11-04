<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWaterBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:99999999'];

        return [
            'bill_year' => ['required', 'integer', 'between:2020,2100'],
            'bill_month' => ['required', 'integer', 'between:1,12'],
            'account_no' => ['nullable', 'string', 'max:50'],
            'bill_no' => ['nullable', 'string', 'max:50'],
            'previous_reading' => ['nullable', 'numeric', 'min:0'],
            'current_reading' => ['nullable', 'numeric', 'min:0', 'gte:previous_reading'],
            'water_charge' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'sewerage_charge' => $money,
            'vat' => $money,
            'other_charge' => $money,
            'late_fee' => $money,
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'treasury_medium' => [$this->isMethod('POST') ? 'required' : 'sometimes', Rule::in(['CASH', 'BANK'])],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'water_charge.required' => 'পানির বিলের টাকা লিখুন।',
            'current_reading.gte' => 'বর্তমান রিডিং আগের রিডিংয়ের চেয়ে কম হতে পারে না।',
            'treasury_medium.required' => 'কোথা থেকে পরিশোধ হবে (ক্যাশ/ব্যাংক) বাছুন।',
            'attachments.*.mimes' => 'শুধু PDF বা ছবি দেওয়া যাবে।',
        ];
    }
}
