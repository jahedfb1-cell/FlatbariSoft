<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Rocket and Upay arrive with the database upgrade (the column was a fixed list before). */
    public static function methods(): array
    {
        $type = collect(\Illuminate\Support\Facades\Schema::getColumns('contributions'))->firstWhere('name', 'method')['type_name'] ?? 'enum';

        return $type === 'enum' ? ['CASH', 'BANK', 'BKASH', 'NAGAD'] : ['CASH', 'BANK', 'BKASH', 'NAGAD', 'ROCKET', 'UPAY'];
    }

    public function rules(): array
    {
        return [
            'contribution_head_id' => ['required', 'integer', 'exists:contribution_heads,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'received_on' => ['required', 'date'],
            'method' => ['required', Rule::in(self::methods())],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'contribution_head_id.required' => 'খাত নির্বাচন আবশ্যক।',
            'amount.required' => 'পরিমাণ আবশ্যক।',
            'amount.min' => 'পরিমাণ শূন্যের বেশি হতে হবে।',
            'received_on.required' => 'তারিখ আবশ্যক।',
        ];
    }
}
