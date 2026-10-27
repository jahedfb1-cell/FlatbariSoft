<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shared by the office form and the resident portal; the portal additionally requires a flat. */
class StoreHallBookingRequest extends FormRequest
{
    public const EVENT_TYPES = ['WEDDING', 'BIRTHDAY', 'RELIGIOUS', 'MEETING', 'COMMUNITY', 'OTHER'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'flat_id' => [$this->is('api/me/*') ? 'required' : 'nullable', 'integer', 'exists:flats,id'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_mobile' => ['required', 'string', 'regex:/^[0-9+\-\s]{7,20}$/'],
            'event_type' => ['required', Rule::in(self::EVENT_TYPES)],
            'guest_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'booking_date' => ['required', 'date'],
            'slot' => ['required', Rule::in(['DAY', 'NIGHT', 'FULL_DAY'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'extra_charge' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'flat_id.required' => 'ফ্ল্যাট নির্বাচন আবশ্যক।',
            'customer_name.required' => 'নাম আবশ্যক।',
            'customer_mobile.required' => 'মোবাইল নম্বর আবশ্যক।',
            'customer_mobile.regex' => 'মোবাইল নম্বর সঠিক নয়।',
            'event_type.required' => 'অনুষ্ঠানের ধরন আবশ্যক।',
            'booking_date.required' => 'তারিখ আবশ্যক।',
            'slot.required' => 'সময় নির্বাচন আবশ্যক।',
        ];
    }
}
