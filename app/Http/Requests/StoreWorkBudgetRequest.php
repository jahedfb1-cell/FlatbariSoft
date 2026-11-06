<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Creating a work (shares included) or previewing the split; the same rules, the preview just needs fewer of them. */
class StoreWorkBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $preview = $this->routeIs('*preview*') || str_ends_with($this->path(), 'preview');

        return [
            'title' => [$preview ? 'nullable' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'estimated_cost' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['PLANNED', 'ONGOING'])],
            'split_method' => ['required', Rule::in(['EQUAL', 'SERVICE_RATE', 'SIZE', 'MANUAL'])],
            'resolution_id' => ['nullable', 'integer', 'exists:resolutions,id'],
            // preview: which flats to split over (omitted = every active flat)
            'flat_ids' => ['nullable', 'array'],
            'flat_ids.*' => ['integer', 'exists:flats,id'],
            // create: the final share of every flat that takes part (0 = waived)
            'shares' => [$preview ? 'nullable' : 'required', 'array', 'min:1'],
            'shares.*.flat_id' => ['required', 'integer', 'distinct', 'exists:flats,id'],
            'shares.*.amount' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'shares.*.note' => ['nullable', 'string', 'max:255'],
            // optional collection schedule: dates with the percentage of each share due then (adds up to 100)
            'installments' => ['nullable', 'array', 'max:24'],
            'installments.*.due_date' => ['required', 'date'],
            'installments.*.percent' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'কাজের নাম লিখুন।',
            'estimated_cost.required' => 'প্রাক্কলিত খরচ লিখুন।',
            'estimated_cost.min' => 'প্রাক্কলিত খরচ শূন্যের বেশি হতে হবে।',
            'end_date.after_or_equal' => 'শেষ তারিখ শুরুর তারিখের আগে হতে পারে না।',
            'shares.required' => 'অন্তত একটি ফ্ল্যাট বাছুন।',
            'shares.min' => 'অন্তত একটি ফ্ল্যাট বাছুন।',
            'shares.*.amount.min' => 'ভাগের পরিমাণ ঋণাত্মক হতে পারে না।',
        ];
    }
}
