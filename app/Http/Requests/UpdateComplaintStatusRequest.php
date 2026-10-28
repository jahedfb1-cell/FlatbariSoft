<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Move a complaint along: change its status, hand it to a staff member, re-rank it, note how it ended. */
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
            'priority' => ['sometimes', Rule::in(['LOW', 'MEDIUM', 'HIGH'])],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'অবস্থা আবশ্যক।',
        ];
    }
}
