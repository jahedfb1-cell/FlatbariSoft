<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequestRequest;
use App\Models\TenantRequest;
use App\Services\TenantRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "This person is renting my flat" requests. Owners and the building manager send them; only an admin
 * (tenant.manage) can accept or reject. Everyone else sees just the requests they sent themselves.
 */
class TenantRequestController extends Controller
{
    public function __construct(private readonly TenantRequestService $service) {}

    private function shape(TenantRequest $r): array
    {
        $r->loadMissing('flat', 'requester', 'decider');

        return [
            'id' => $r->id, 'flat_id' => $r->flat_id, 'flat_no' => $r->flat?->flat_no,
            'name_bn' => $r->name_bn, 'name_en' => $r->name_en, 'mobile' => $r->mobile, 'nid' => $r->nid,
            'start_date' => $r->start_date?->toDateString(), 'monthly_rent' => $r->monthly_rent, 'security_deposit' => $r->security_deposit,
            'occupation' => $r->occupation, 'family_members_count' => $r->family_members_count, 'note' => $r->note,
            'status' => $r->status, 'reject_reason' => $r->reject_reason, 'tenant_id' => $r->tenant_id,
            'requested_by' => $r->requester?->displayName(),
            'requested_at' => $r->created_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i'),
            'decided_by' => $r->decider?->displayName(),
            'decided_at' => $r->decided_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i'),
        ];
    }

    private function isAdmin(): bool
    {
        $u = Auth::user();

        return $u->hasRole('super_admin') || $u->can('tenant.manage');
    }

    /** An admin sees every request (?status= narrows); anyone else sees only their own. Waiting ones first. */
    public function index(Request $request)
    {
        $rows = TenantRequest::with('flat', 'requester', 'decider')
            ->when(! $this->isAdmin(), fn ($q) => $q->where('requested_by', Auth::id()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByRaw("CASE status WHEN 'PENDING' THEN 0 ELSE 1 END")
            ->orderByDesc('id')->limit(200)->get();

        return response()->json([
            'can_decide' => $this->isAdmin(),
            'requests' => $rows->map(fn ($r) => $this->shape($r)),
        ]);
    }

    public function store(StoreTenantRequestRequest $request)
    {
        return response()->json($this->shape($this->service->submit(Auth::user(), $request->validated())), 201);
    }

    public function approve(TenantRequest $tenantRequest)
    {
        return response()->json($this->shape($this->service->approve($tenantRequest, Auth::user())));
    }

    public function reject(Request $request, TenantRequest $tenantRequest)
    {
        $v = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']], ['reason.required' => 'কেন বাতিল করছেন তা লিখুন।']);

        return response()->json($this->shape($this->service->reject($tenantRequest, Auth::user(), $v['reason'])));
    }

    public function cancel(TenantRequest $tenantRequest)
    {
        abort_unless($tenantRequest->requested_by === Auth::id() || $this->isAdmin(), 403);

        return response()->json($this->shape($this->service->cancel($tenantRequest, Auth::user())));
    }
}
