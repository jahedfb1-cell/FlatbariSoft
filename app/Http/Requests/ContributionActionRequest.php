<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Small contribution actions on one class, switched on the controller method:
 *  reverse          -> reason
 *  setFlatTarget    -> target_amount (null = back to the default), note
 *  setDefaultTarget -> target_amount
 *  import           -> as_of, rows[], dry_run
 */
class ContributionActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'reverse' => ['reason' => ['required', 'string', 'max:300']],
            'setFlatTarget' => ['target_amount' => ['present', 'nullable', 'numeric', 'min:0'], 'note' => ['nullable', 'string', 'max:300']],
            'setDefaultTarget' => ['target_amount' => ['required', 'numeric', 'min:0']],
            default => [
                'as_of' => ['required', 'date'],
                'dry_run' => ['sometimes', 'boolean'],
                'rows' => ['required', 'array', 'min:1', 'max:500'],
                'rows.*.flat_no' => ['required', 'string', 'max:20'],
                'rows.*.amounts' => ['required', 'array'],
                'rows.*.amounts.*' => ['nullable', 'numeric', 'min:0'],
            ],
        };
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'কারণ লিখুন।',
            'target_amount.required' => 'টার্গেটের পরিমাণ আবশ্যক।',
            'as_of.required' => 'কোন তারিখ পর্যন্ত জমা — তারিখ আবশ্যক।',
            'rows.required' => 'কোনো সারি নেই।',
        ];
    }
}
