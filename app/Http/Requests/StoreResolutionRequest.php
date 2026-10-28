<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A resolution is typed text, an uploaded PDF / photo, or both - but never empty. */
class StoreResolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'required_without:attachment'],
            'decision' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required_without' => 'বিবরণ লিখুন, অথবা PDF/ছবি যুক্ত করুন।',
            'attachment.mimes' => 'শুধু PDF বা ছবি (JPG, PNG, WEBP) দেওয়া যাবে।',
            'attachment.max' => 'ফাইল সর্বোচ্চ ১০ MB হতে পারবে।',
        ];
    }
}
