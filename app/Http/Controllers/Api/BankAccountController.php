<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankAccountRequest;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;

class BankAccountController extends Controller
{
    /**
     * The flat owners an account holder can be picked from: one entry per person, with the flats they own and whether they can log in.
     * Names and phones always come from the person's own record.
     */
    public function owners()
    {
        $parties = \App\Models\FlatParty::with('person', 'flat')->where('role', 'OWNER')->whereNull('to_date')->get();
        $withLogin = \App\Models\User::whereNotNull('person_id')->pluck('person_id')->flip();

        return response()->json($parties->groupBy('person_id')->map(function ($rows) use ($withLogin) {
            $p = $rows->first()->person;

            return [
                'person_id' => $p->id, 'name' => $p->name_en ?: $p->name_bn, 'mobile' => $p->mobile,
                'flats' => $rows->map(fn ($r) => $r->flat?->flat_no)->filter()->sort(SORT_NATURAL)->implode(', '),
                'has_login' => $withLogin->has($p->id),
            ];
        })->sortBy('flats', SORT_NATURAL)->values());
    }

    /** A holder picked from the owners is filled in from that person's record; anyone who is not a current flat owner is refused. */
    private function withHolders(array $data): array
    {
        $upgraded = \Illuminate\Support\Facades\Schema::hasColumn('bank_accounts', 'holder_1_person_id');
        foreach ([1, 2, 3] as $n) {
            $key = "holder_{$n}_person_id";
            if (! $upgraded) {
                unset($data[$key]);

                continue;
            }
            if (! empty($data[$key])) {
                $person = \App\Models\Person::findOrFail($data[$key]);
                $isOwner = \App\Models\FlatParty::where('person_id', $person->id)->where('role', 'OWNER')->whereNull('to_date')->exists();
                if (! $isOwner) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$key => 'অ্যাকাউন্ট হোল্ডার ফ্ল্যাট মালিকদের মধ্য থেকে বাছতে হবে।']);
                }
                $data["holder_{$n}_name"] = $person->name_en ?: $person->name_bn;
                $data["holder_{$n}_phone"] = $person->mobile;
            } elseif (array_key_exists($key, $data)) {
                $data[$key] = null; // a holder typed in by hand is not linked to anyone
            }
        }

        return $data;
    }

    public function index()
    {
        return BankAccountResource::collection(BankAccount::all());
    }

    public function store(StoreBankAccountRequest $request)
    {
        $opening = $request->validated('opening_balance', 0);

        $account = BankAccount::create(array_merge($this->withHolders($request->validated()), [
            'opening_balance' => $opening,
            'current_balance' => $opening,
        ]));

        return new BankAccountResource($account);
    }

    /** Edit the details of an account (holders, phones, branch ...). The balance is never edited here. */
    public function update(StoreBankAccountRequest $request, BankAccount $bankAccount)
    {
        $bankAccount->update($this->withHolders($request->validated()));

        return new BankAccountResource($bankAccount->fresh());
    }
}
