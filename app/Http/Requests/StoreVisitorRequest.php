<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'flat_id' => ['required', 'exists:flats,id'],
            'visitor_name' => ['required', 'string', 'max:120'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'purpose' => ['required', Rule::in(['GUEST', 'DELIVERY', 'SERVICE', 'OTHER'])],
            'vehicle_no' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['flat_id.required' => 'ফ্ল্যাট বেছে নিন।', 'visitor_name.required' => 'দর্শনার্থীর নাম লিখুন।'];
    }
}
