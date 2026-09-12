<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplaintStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'])],
            'assigned_to' => ['nullable', 'exists:staff,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'অবস্থা আবশ্যক।',
        ];
    }
}
