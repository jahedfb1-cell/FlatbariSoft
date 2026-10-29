<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The tenant KYC form: who they are (NID, phones, work), where they came from, who to call, and everyone
 * who will live in the flat. Used by the tenant themselves and by the office filling it in for them.
 */
class TenantKycRequest extends FormRequest
{
    public const RELATIONS = ['SPOUSE', 'SON', 'DAUGHTER', 'FATHER', 'MOTHER', 'BROTHER', 'SISTER', 'RELATIVE', 'OTHER'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mobile = ['nullable', 'string', 'regex:/^01[3-9]\d{8}$/'];

        return [
            'person.name_bn' => ['required', 'string', 'max:255'],
            'person.name_en' => ['nullable', 'string', 'max:255'],
            // Bangladeshi NID: 10 (smart card), 13 (old) or 17 digits (old with birth year)
            'person.nid' => ['required', 'string', 'regex:/^(\d{10}|\d{13}|\d{17})$/'],
            'person.alt_mobile' => $mobile,
            'person.email' => ['nullable', 'email', 'max:255'],
            'person.address' => ['required', 'string', 'max:1000'],
            'occupation' => ['required', 'string', 'max:100'],
            'workplace' => ['nullable', 'string', 'max:255'],
            'emergency_name' => ['required', 'string', 'max:255'],
            'emergency_phone' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'previous_address' => ['nullable', 'string', 'max:1000'],
            'reference_name' => ['nullable', 'string', 'max:255'],
            'reference_phone' => $mobile,
            'other_info' => ['nullable', 'string', 'max:2000'],
            'family_members' => ['nullable', 'array', 'max:15'],
            'family_members.*.name' => ['required', 'string', 'max:255'],
            'family_members.*.relation' => ['required', Rule::in(self::RELATIONS)],
            'family_members.*.age' => ['nullable', 'integer', 'between:0,120'],
            'family_members.*.nid' => ['nullable', 'string', 'regex:/^(\d{10}|\d{13}|\d{17})$/'],
            'family_members.*.mobile' => $mobile,
            'family_members.*.occupation' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'person.name_bn.required' => 'নাম লিখুন।',
            'person.nid.required' => 'জাতীয় পরিচয়পত্র (NID) নম্বর দিন।',
            'person.nid.regex' => 'NID নম্বর ১০, ১৩ বা ১৭ অঙ্কের হতে হবে (শুধু সংখ্যা)।',
            'person.address.required' => 'স্থায়ী ঠিকানা দিন।',
            'occupation.required' => 'পেশা লিখুন।',
            'emergency_name.required' => 'জরুরি যোগাযোগের ব্যক্তির নাম দিন।',
            'emergency_phone.required' => 'জরুরি যোগাযোগের ফোন নম্বর দিন।',
            '*.regex' => 'সঠিক নম্বর দিন।',
            'family_members.*.name.required' => 'পরিবারের সদস্যের নাম দিন।',
            'family_members.*.relation.required' => 'সদস্যের সাথে সম্পর্ক বেছে নিন।',
            'family_members.max' => 'সর্বোচ্চ ১৫ জন সদস্য দেওয়া যাবে।',
        ];
    }
}
