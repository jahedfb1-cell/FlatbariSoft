<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseAttachmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attachments' => ['required', 'array', 'min:1', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.required' => 'ফাইল বেছে নিন।',
            'attachments.max' => 'একবারে সর্বোচ্চ ৫টা ফাইল দেওয়া যাবে।',
            'attachments.*.mimes' => 'শুধু PDF বা ছবি (JPG, PNG, WEBP) দেওয়া যাবে।',
            'attachments.*.max' => 'প্রতিটি ফাইল সর্বোচ্চ ১০ MB হতে পারবে।',
        ];
    }
}
