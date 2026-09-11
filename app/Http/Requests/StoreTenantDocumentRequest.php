<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(['NID', 'PHOTO', 'POLICE_VERIFICATION'])],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_type.required' => 'ডকুমেন্টের ধরন আবশ্যক।',
            'file.required' => 'ফাইল আবশ্যক।',
            'file.mimes' => 'শুধু jpg, png, বা pdf ফাইল আপলোড করা যাবে।',
            'file.max' => 'ফাইলের সাইজ সর্বোচ্চ 5MB হতে পারবে।',
        ];
    }
}
