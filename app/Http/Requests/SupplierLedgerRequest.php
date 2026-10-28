<?php

namespace App\Http\Requests;

use App\Services\SupplierLedgerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** The kind (gas / materials) is fixed by the route, never by the caller. */
    protected function prepareForValidation(): void
    {
        $this->merge(['kind' => $this->route('kind')]);
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(SupplierLedgerService::KINDS)],
            'supplier' => ['sometimes', 'required', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    public function messages(): array
    {
        return ['to.after_or_equal' => 'শেষ তারিখ শুরুর তারিখের আগে হতে পারে না।'];
    }
}
