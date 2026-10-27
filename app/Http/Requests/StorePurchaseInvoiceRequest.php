<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Attach (or replace) the invoice photo on an existing purchase. */
class StorePurchaseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['invoice_image' => InvoiceImageRules::rules(required: true)];
    }

    public function messages(): array
    {
        return InvoiceImageRules::messages();
    }
}
