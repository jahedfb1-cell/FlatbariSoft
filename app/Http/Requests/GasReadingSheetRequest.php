<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The month's meter-reading sheet: reading it, saving it, downloading the Excel template, checking an uploaded file. */
class GasReadingSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $month = [
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ];

        return match ($this->route()->getActionMethod()) {
            'saveReadingSheet' => $month + [
                'reading_date' => ['nullable', 'date'],
                'rows' => ['required', 'array', 'min:1', 'max:500'],
                'rows.*.flat_no' => ['nullable', 'string', 'max:30'],
                'rows.*.consumer_id' => ['nullable', 'integer'],
                'rows.*.previous_reading' => ['nullable', 'numeric', 'min:0'],
                'rows.*.current_reading' => ['nullable', 'numeric', 'min:0'],
                'rows.*.units' => ['nullable', 'numeric', 'min:0'],
            ],
            'importReadings' => $month + [
                'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv,txt'],
            ],
            default => $month,
        };
    }

    public function messages(): array
    {
        return [
            'file.required' => 'এক্সেল ফাইল বাছুন।',
            'file.mimes' => 'ফাইলটি Excel (.xlsx, .xls) বা CSV হতে হবে।',
            'rows.required' => 'কোনো রিডিং লেখা হয়নি।',
        ];
    }
}
