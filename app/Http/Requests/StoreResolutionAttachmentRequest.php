<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResolutionAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['attachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']];
    }

    public function messages(): array
    {
        return [
            'attachment.required' => 'PDF বা ছবি বেছে নিন।',
            'attachment.mimes' => 'শুধু PDF বা ছবি (JPG, PNG, WEBP) দেওয়া যাবে।',
            'attachment.max' => 'ফাইল সর্বোচ্চ ১০ MB হতে পারবে।',
        ];
    }
}
