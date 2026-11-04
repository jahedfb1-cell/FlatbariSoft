<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierMasterController extends Controller
{
    public function __construct(private readonly SupplierService $service) {}

    public function index(Request $request)
    {
        $v = $request->validate(['kind' => ['nullable', Rule::in(Supplier::KINDS)], 'active' => ['nullable', 'boolean']]);

        return response()->json($this->service->list($v['kind'] ?? null, (bool) ($v['active'] ?? false)));
    }

    private function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'max:255'],
            'kind' => [$req, Rule::in(Supplier::KINDS)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]{6,20}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function store(Request $request)
    {
        $s = $this->service->create($request->validate($this->rules(true), ['name.required' => 'সরবরাহকারীর নাম লিখুন।', 'phone.regex' => 'সঠিক ফোন নম্বর দিন।']));

        return response()->json($this->service->present($s), 201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        return response()->json($this->service->present($this->service->update($supplier, $request->validate($this->rules(false)))));
    }
}
