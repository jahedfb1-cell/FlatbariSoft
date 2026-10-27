<?php

namespace App\Http\Requests;

use App\Models\OwnerDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOwnerDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'flat_id' => ['required', 'exists:flats,id'],
            'document_type' => ['required', Rule::in(['NID', 'NAMJARI', 'LAND_TAX_RECEIPT', 'OTHER'])],
            // NID is a two-sided card — front/back print together on one
            // sheet, so the system needs to know which image is which.
            'side' => ['required_if:document_type,NID', 'nullable', Rule::in(['FRONT', 'BACK'])],
            'document_year' => ['required_if:document_type,LAND_TAX_RECEIPT', 'nullable', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'issued_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issued_date'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'flat_id.required' => 'ফ্ল্যাট নির্বাচন আবশ্যক।',
            'document_type.required' => 'ডকুমেন্টের ধরন আবশ্যক।',
            'side.required_if' => 'NID-এর কোন পাশ (সামনে/পেছনে) তা নির্বাচন আবশ্যক।',
            'document_year.required_if' => 'জমির খাজনা রসিদের জন্য বছর আবশ্যক।',
            'file.required' => 'ফাইল আবশ্যক।',
            'file.mimes' => 'শুধু jpg, png, বা pdf ফাইল আপলোড করা যাবে।',
            'file.max' => 'ফাইলের সাইজ সর্বোচ্চ 5MB হতে পারবে।',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // BR-16: land tax receipt is one new entry per year, never
            // overwritten — but it also shouldn't accidentally duplicate
            // the same year twice for the same flat.
            if ($this->document_type === 'LAND_TAX_RECEIPT' && $this->document_year && $this->flat_id) {
                $exists = OwnerDocument::where('flat_id', $this->flat_id)
                    ->where('document_type', 'LAND_TAX_RECEIPT')
                    ->where('document_year', $this->document_year)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'document_year',
                        "এই ফ্ল্যাটের {$this->document_year} সালের খাজনা রসিদ আগেই আপলোড করা আছে।",
                    );
                }
            }

            // A person's NID has exactly one FRONT and one BACK — a second
            // upload of the same side must replace (delete first), not pile up.
            if ($this->document_type === 'NID' && $this->side) {
                $exists = OwnerDocument::where('person_id', $this->route('person')?->id)
                    ->where('document_type', 'NID')
                    ->where('side', $this->side)
                    ->exists();

                if ($exists) {
                    $sideLabel = $this->side === 'FRONT' ? 'সামনের' : 'পেছনের';
                    $validator->errors()->add(
                        'side',
                        "এই ব্যক্তির NID-এর {$sideLabel} পাশ আগেই আপলোড করা আছে — নতুন করে দিতে হলে আগে সেটা মুছুন।",
                    );
                }
            }
        });
    }
}
