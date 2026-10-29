<?php

namespace App\Http\Requests;

use App\Models\FlatParty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * "This person is renting my flat." A flat owner may name only a flat they currently own; someone with the
 * tenant.request permission (the building manager) may name any flat. The same mobile number cannot be
 * requested twice at once, and a flat with a request already waiting cannot get a second one.
 */
class StoreTenantRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = Auth::user();
        $any = $user->hasRole('super_admin') || $user->can('tenant.request');
        $ownFlats = $user->person_id
            ? FlatParty::where('person_id', $user->person_id)->where('role', 'OWNER')->whereNull('to_date')->pluck('flat_id')->all()
            : [];

        return [
            'flat_id' => ['required', 'integer', $any ? 'exists:flats,id' : Rule::in($ownFlats)],
            'name_bn' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'nid' => ['nullable', 'string', 'regex:/^(\d{10}|\d{13}|\d{17})$/'],
            'start_date' => ['required', 'date'],
            'monthly_rent' => ['nullable', 'numeric', 'min:0'],
            'security_deposit' => ['nullable', 'numeric', 'min:0'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'family_members_count' => ['nullable', 'integer', 'min:0', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'flat_id.in' => 'এটি আপনার ফ্ল্যাট নয়।',
            'flat_id.required' => 'ফ্ল্যাট বেছে নিন।',
            'name_bn.required' => 'ভাড়াটিয়ার নাম লিখুন।',
            'mobile.required' => 'ভাড়াটিয়ার মোবাইল নম্বর দিন।',
            'mobile.regex' => 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন (01XXXXXXXXX)।',
            'nid.regex' => 'NID নম্বর ১০, ১৩ বা ১৭ অঙ্কের হতে হবে।',
            'start_date.required' => 'ভাড়া শুরুর তারিখ দিন।',
        ];
    }
}
