<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EndOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_date' => ['nullable', 'date'],
        ];
    }
}
