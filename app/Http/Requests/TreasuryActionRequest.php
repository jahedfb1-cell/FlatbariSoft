<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Every treasury action on one class, switched on the controller method:
 *  openingBalance            fund, cash, bank, date, notes
 *  deposit/withdraw/profit   fund, amount, date, notes
 *  lend / repay              from_fund (the one that pays out), to_fund, medium, amount, date, notes
 *  reverse                   reason
 */
class TreasuryActionRequest extends FormRequest
{
    private const FUNDS = ['OPERATING', 'GAS', 'CAPITAL', 'SINKING'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $common = [
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:300'],
        ];

        return match ($this->route()->getActionMethod()) {
            'openingBalance' => $common + [
                'fund' => ['required', Rule::in(self::FUNDS)],
                'cash' => ['required', 'numeric', 'min:0'],
                'bank' => ['required', 'numeric', 'min:0'],
            ],
            'lend', 'repay' => $common + [
                'from_fund' => ['required', Rule::in(self::FUNDS)],
                'to_fund' => ['required', Rule::in(self::FUNDS), 'different:from_fund'],
                'medium' => ['required', Rule::in(['CASH', 'BANK'])],
                'amount' => ['required', 'numeric', 'min:0.01'],
            ],
            'reverse' => ['reason' => ['required', 'string', 'max:300']],
            default => $common + [
                'fund' => ['required', Rule::in(self::FUNDS)],
                'amount' => ['required', 'numeric', 'min:0.01'],
            ],
        };
    }

    public function messages(): array
    {
        return [
            'fund.required' => 'তহবিল নির্বাচন আবশ্যক।',
            'amount.required' => 'পরিমাণ আবশ্যক।',
            'amount.min' => 'পরিমাণ শূন্যের বেশি হতে হবে।',
            'date.required' => 'তারিখ আবশ্যক।',
            'to_fund.different' => 'একই তহবিলে ধার দেওয়া যায় না।',
            'reason.required' => 'কারণ লিখুন।',
        ];
    }
}
