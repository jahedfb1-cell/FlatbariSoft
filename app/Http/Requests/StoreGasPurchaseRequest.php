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
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplier_name' => ['required_without:supplier_id', 'nullable', 'string', 'max:255'],
            // gas is bought by the cylinder: how many, how many kg in each, and the price of one - or, the old way, kg and a price per kg
            'cylinders' => ['nullable', 'integer', 'min:1', 'max:1000', 'required_with:cylinder_kg,price_per_cylinder'],
            'cylinder_kg' => ['nullable', 'numeric', 'min:0.1', 'max:1000', 'required_with:cylinders'],
            'price_per_cylinder' => ['nullable', 'numeric', 'min:1', 'max:99999999', 'required_with:cylinders'],
            'quantity_kg' => ['required_without:cylinders', 'nullable', 'numeric', 'min:0.01'],
            'unit_price' => ['required_without:cylinders', 'nullable', 'numeric', 'min:0.01'],
            'transport_cost' => ['nullable', 'numeric', 'min:0'],
            'treasury_medium' => ['required', Rule::in(['CASH', 'BANK'])],
            'invoice_image' => InvoiceImageRules::rules(),
        ];
    }

    public function messages(): array
    {
        return InvoiceImageRules::messages() + [
            'date.required' => 'তারিখ আবশ্যক।',
            'supplier_name.required' => 'সরবরাহকারীর নাম আবশ্যক।',
            'quantity_kg.required' => 'পরিমাণ (কেজি) আবশ্যক।',
            'unit_price.required' => 'ইউনিট মূল্য আবশ্যক।',
            'treasury_medium.required' => 'পেমেন্ট মাধ্যম (নগদ/ব্যাংক) আবশ্যক।',
        ];
    }
}
