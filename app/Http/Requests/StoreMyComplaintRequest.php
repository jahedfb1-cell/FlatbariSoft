<?php

namespace App\Http\Requests;

use App\Models\FlatParty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * flat_id must be one of the logged-in resident's own flats — the same
 * scoping MyController's other endpoints use (FlatParty rows for their
 * person_id with no end date), duplicated here rather than shared,
 * since a Form Request resolves its own rules independently of the
 * controller it feeds.
 */
class StoreMyComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $personId = Auth::user()->person_id;
        $flatIds = $personId
            ? FlatParty::where('person_id', $personId)->whereNull('to_date')->pluck('flat_id')->all()
            : [];

        return [
            'flat_id' => ['required', Rule::in($flatIds)],
            'category' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'flat_id.required' => 'ফ্ল্যাট নির্বাচন আবশ্যক।',
            'flat_id.in' => 'এই ফ্ল্যাটটি আপনার নয়।',
            'category.required' => 'অভিযোগের ধরন আবশ্যক।',
            'description.required' => 'বিবরণ আবশ্যক।',
        ];
    }
}
