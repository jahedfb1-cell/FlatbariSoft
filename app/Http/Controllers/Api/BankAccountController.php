<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankAccountRequest;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;

class BankAccountController extends Controller
{
    public function index()
    {
        return BankAccountResource::collection(BankAccount::all());
    }

    public function store(StoreBankAccountRequest $request)
    {
        $opening = $request->validated('opening_balance', 0);

        $account = BankAccount::create(array_merge($request->validated(), [
            'opening_balance' => $opening,
            'current_balance' => $opening,
        ]));

        return new BankAccountResource($account);
    }

    /** Edit the details of an account (holders, phones, branch ...). The balance is never edited here. */
    public function update(StoreBankAccountRequest $request, BankAccount $bankAccount)
    {
        $bankAccount->update($request->validated());

        return new BankAccountResource($bankAccount->fresh());
    }
}
