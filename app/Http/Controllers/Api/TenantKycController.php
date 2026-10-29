<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TenantKycRequest;
use App\Models\Tenant;
use App\Services\TenantKycService;
use Illuminate\Support\Facades\Auth;

/** The tenant KYC form: the office reads and fills any tenant's; a tenant reads and fills only their own. */
class TenantKycController extends Controller
{
    public function __construct(private readonly TenantKycService $kyc) {}

    public function show(Tenant $tenant)
    {
        return response()->json($this->kyc->present($tenant));
    }

    public function update(TenantKycRequest $request, Tenant $tenant)
    {
        return response()->json($this->kyc->present($this->kyc->save($tenant, $request->validated())));
    }

    /** The logged-in tenant's current tenancy, or null when they are not a tenant right now. */
    private function mine(): ?Tenant
    {
        $personId = Auth::user()->person_id;

        return $personId ? Tenant::where('person_id', $personId)->where('status', 'ACTIVE')->latest('id')->first() : null;
    }

    public function showMine()
    {
        $tenant = $this->mine();

        return response()->json($tenant ? $this->kyc->present($tenant) : null);
    }

    public function updateMine(TenantKycRequest $request)
    {
        $tenant = $this->mine();
        abort_unless($tenant, 404, 'আপনার নামে কোনো সক্রিয় ভাড়াটিয়া তথ্য নেই।');

        return response()->json($this->kyc->present($this->kyc->save($tenant, $request->validated())));
    }
}
