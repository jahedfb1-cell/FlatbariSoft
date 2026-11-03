<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['CV', 'NID', 'PHOTO', 'CERTIFICATE', 'OTHER'])],
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'ফাইলের ধরন বাছুন।',
            'files.required' => 'ফাইল বাছুন।',
            'files.*.mimes' => 'শুধু PDF বা ছবি (JPG, PNG, WEBP) দেওয়া যাবে।',
            'files.*.max' => 'প্রতিটি ফাইল সর্বোচ্চ ১০ MB হতে পারে।',
        ];
    }
}
