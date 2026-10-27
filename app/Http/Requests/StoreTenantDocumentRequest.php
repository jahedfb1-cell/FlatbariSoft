<?php

namespace App\Http\Requests;

use App\Models\TenantDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'side' => ['required_if:document_type,NID', 'nullable', Rule::in(['FRONT', 'BACK'])],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_type.required' => 'ডকুমেন্টের ধরন আবশ্যক।',
            'side.required_if' => 'NID-এর কোন পাশ (সামনে/পেছনে) তা নির্বাচন আবশ্যক।',
            'file.required' => 'ফাইল আবশ্যক।',
            'file.mimes' => 'শুধু jpg, png, বা pdf ফাইল আপলোড করা যাবে।',
            'file.max' => 'ফাইলের সাইজ সর্বোচ্চ 5MB হতে পারবে।',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->document_type === 'NID' && $this->side) {
                $exists = TenantDocument::where('tenant_id', $this->route('tenant')?->id)
                    ->where('document_type', 'NID')
                    ->where('side', $this->side)
                    ->exists();

                if ($exists) {
                    $sideLabel = $this->side === 'FRONT' ? 'সামনের' : 'পেছনের';
                    $validator->errors()->add(
                        'side',
                        "এই ভাড়াটিয়ার NID-এর {$sideLabel} পাশ আগেই আপলোড করা আছে — নতুন করে দিতে হলে আগে সেটা মুছুন।",
                    );
                }
            }
        });
    }
}
