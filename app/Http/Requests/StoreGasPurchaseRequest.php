<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGasPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'quantity_kg' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0.01'],
            'transport_cost' => ['nullable', 'numeric', 'min:0'],
            'treasury_medium' => ['required', Rule::in(['CASH', 'BANK'])],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'তারিখ আবশ্যক।',
            'supplier_name.required' => 'সরবরাহকারীর নাম আবশ্যক।',
            'quantity_kg.required' => 'পরিমাণ (কেজি) আবশ্যক।',
            'unit_price.required' => 'ইউনিট মূল্য আবশ্যক।',
            'treasury_medium.required' => 'পেমেন্ট মাধ্যম (নগদ/ব্যাংক) আবশ্যক।',
        ];
    }
}
