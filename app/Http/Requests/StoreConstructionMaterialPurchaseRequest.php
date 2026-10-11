<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConstructionMaterialPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'item_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['required', 'string', 'max:50'],
            'unit_rate' => ['required', 'numeric', 'min:0.01'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'transport_cost' => ['nullable', 'numeric', 'min:0'],
            'treasury_medium' => ['required', Rule::in(['CASH', 'BANK'])],
            'invoice_image' => InvoiceImageRules::rules(),
        ];
    }

    public function messages(): array
    {
        return InvoiceImageRules::messages() + [
            'date.required' => 'তারিখ আবশ্যক।',
            'item_name.required' => 'মালামালের নাম আবশ্যক।',
            'quantity.required' => 'পরিমাণ আবশ্যক।',
            'unit.required' => 'একক (যেমন: বস্তা, কেজি, টন) আবশ্যক।',
            'unit_rate.required' => 'একক মূল্য আবশ্যক।',
            'treasury_medium.required' => 'পেমেন্ট মাধ্যম (নগদ/ব্যাংক) আবশ্যক।',
        ];
    }
}
