<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The KYC form of a staff member. Everything is optional to save; the page shows what is still missing. */
class StaffKycRequest extends FormRequest
{
    public const RELATIONS = ['SPOUSE', 'SON', 'DAUGHTER', 'FATHER', 'MOTHER', 'BROTHER', 'SISTER', 'RELATIVE', 'OTHER'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mobile = ['nullable', 'string', 'regex:/^01[3-9]\d{8}$/'];
        $text = ['nullable', 'string', 'max:255'];

        return [
            'nid' => ['nullable', 'string', 'regex:/^(\d{10}|\d{13}|\d{17})$/'],
            'father_name' => $text,
            'mother_name' => $text,
            'spouse_name' => $text,
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => ['nullable', Rule::in(['MALE', 'FEMALE', 'OTHER'])],
            'marital_status' => ['nullable', Rule::in(['SINGLE', 'MARRIED', 'WIDOWED', 'DIVORCED'])],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'religion' => ['nullable', 'string', 'max:30'],
            'alt_mobile' => $mobile,
            'email' => ['nullable', 'email', 'max:255'],
            'present_address' => ['nullable', 'string', 'max:1000'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'emergency_name' => $text,
            'emergency_phone' => $mobile,
            'emergency_relation' => ['nullable', 'string', 'max:50'],
            'education' => $text,
            'experience' => ['nullable', 'string', 'max:2000'],
            'reference_name' => $text,
            'reference_phone' => $mobile,
            'other_info' => ['nullable', 'string', 'max:2000'],
            'family_members' => ['nullable', 'array', 'max:15'],
            'family_members.*.name' => ['required', 'string', 'max:255'],
            'family_members.*.relation' => ['required', Rule::in(self::RELATIONS)],
            'family_members.*.age' => ['nullable', 'integer', 'between:0,120'],
            'family_members.*.mobile' => $mobile,
            'family_members.*.occupation' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'nid.regex' => 'NID ১০, ১৩ বা ১৭ অঙ্কের হতে হবে।',
            'mobile.regex' => 'সঠিক মোবাইল নম্বর দিন (01XXXXXXXXX)।',
            'alt_mobile.regex' => 'সঠিক মোবাইল নম্বর দিন (01XXXXXXXXX)।',
            'emergency_phone.regex' => 'জরুরি যোগাযোগের নম্বর সঠিক নয়।',
            'reference_phone.regex' => 'রেফারেন্সের নম্বর সঠিক নয়।',
            'date_of_birth.before' => 'জন্ম তারিখ আজকের আগের হতে হবে।',
            'family_members.*.name.required' => 'পরিবারের সদস্যের নাম লিখুন।',
            'family_members.*.relation.required' => 'পরিবারের সদস্যের সম্পর্ক বাছুন।',
        ];
    }
}
