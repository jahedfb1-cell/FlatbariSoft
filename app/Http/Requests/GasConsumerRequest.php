<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Adding or editing a gas consumer: a flat's meter, or any other connection such as the project office. */
class GasConsumerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'flat_id' => [$creating ? 'nullable' : 'prohibited', 'integer', 'exists:flats,id'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'meter_no' => ['nullable', 'string', 'max:100'],
            // an internal consumer (project office) is never billed to a flat
            'is_internal' => [$creating ? 'sometimes' : 'prohibited', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'গ্রাহকের নাম আবশ্যক।',
            'flat_id.exists' => 'এই ফ্ল্যাট পাওয়া যায়নি।',
        ];
    }
}
