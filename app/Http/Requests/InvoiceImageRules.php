<?php

namespace App\Http\Requests;

/**
 * One definition of "a valid invoice photo" shared by every request that
 * accepts one (gas purchase, material purchase, attach-later upload).
 */
class InvoiceImageRules
{
    public static function rules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:jpg,jpeg,png,webp',
            'max:'.config('invoice_images.max_upload_kb'),
        ];
    }

    public static function messages(): array
    {
        return [
            'invoice_image.required' => 'ইনভয়েসের ছবি আবশ্যক।',
            'invoice_image.file' => 'ইনভয়েসের ছবিটি সঠিক ফাইল নয়।',
            'invoice_image.mimes' => 'শুধু JPG, PNG বা WebP ছবি দেওয়া যাবে।',
            'invoice_image.max' => 'ছবির সাইজ '.(int) (config('invoice_images.max_upload_kb') / 1024).' MB-এর বেশি হতে পারবে না।',
            // PHP rejects the upload itself (php.ini upload_max_filesize) before Laravel sees a file.
            'invoice_image.uploaded' => 'ছবি আপলোড হয়নি — ফাইলটি সার্ভারের আপলোড সীমার চেয়ে বড় হতে পারে।',
        ];
    }
}
