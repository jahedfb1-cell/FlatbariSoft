<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConstructionDueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'flat_id' => ['required', 'exists:flats,id'],
            'category' => ['required', 'string', 'max:255'],
            'source_type' => ['required', Rule::in(['INITIAL_CONTRACT', 'LEVY_RESOLUTION'])],
            // Confirmed rule #10: a levy (উন্নয়ন তহবিল) always ties back to
            // the meeting resolution that authorized it.
            'resolution_id' => ['required_if:source_type,LEVY_RESOLUTION', 'nullable', 'exists:resolutions,id'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'installments' => ['required', 'array', 'min:1'],
            'installments.*.due_date' => ['required', 'date'],
            'installments.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
