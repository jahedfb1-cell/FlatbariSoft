<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenantVacateRequest;
use App\Services\TenantVacateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** "My tenant is leaving" requests: sent by the owner or the manager, accepted or rejected by an admin. */
class TenantVacateRequestController extends Controller
{
    public function __construct(private readonly TenantVacateService $service) {}

    private function isAdmin(): bool
    {
        $u = Auth::user();

        return $u->hasRole('super_admin') || $u->can('tenant.manage');
    }

    private function shape(TenantVacateRequest $r): array
    {
        $r->loadMissing('flat', 'tenant.person', 'requester', 'decider');

        return [
            'id' => $r->id, 'flat_id' => $r->flat_id, 'flat_no' => $r->flat?->flat_no, 'tenant_id' => $r->tenant_id,
            'tenant_name' => $r->tenant?->person?->name_bn, 'tenant_mobile' => $r->tenant?->person?->mobile,
            'end_date' => $r->end_date?->toDateString(), 'note' => $r->note, 'status' => $r->status, 'reject_reason' => $r->reject_reason,
            'requested_by' => $r->requester?->displayName(),
            'requested_at' => $r->created_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i'),
            'decided_by' => $r->decider?->displayName(),
            'decided_at' => $r->decided_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i'),
        ];
    }

    /** The tenants this person may report as leaving, plus the requests (an admin sees all, anyone else only their own). */
    public function index()
    {
        $rows = TenantVacateRequest::with('flat', 'tenant.person', 'requester', 'decider')
            ->when(! $this->isAdmin(), fn ($q) => $q->where('requested_by', Auth::id()))
            ->orderByRaw("CASE status WHEN 'PENDING' THEN 0 ELSE 1 END")->orderByDesc('id')->limit(200)->get();

        return response()->json([
            'can_decide' => $this->isAdmin(),
            'tenants' => $this->service->tenantsFor(Auth::user()),
            'requests' => $rows->map(fn ($r) => $this->shape($r)),
        ]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'end_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], ['tenant_id.required' => 'ভাড়াটিয়া বেছে নিন।', 'end_date.required' => 'ছাড়ার তারিখ দিন।']);

        return response()->json($this->shape($this->service->submit(Auth::user(), $v['tenant_id'], $v['end_date'], $v['note'] ?? null)), 201);
    }

    public function approve(TenantVacateRequest $vacateRequest)
    {
        return response()->json($this->shape($this->service->approve($vacateRequest, Auth::user())));
    }

    public function reject(Request $request, TenantVacateRequest $vacateRequest)
    {
        $v = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']], ['reason.required' => 'কেন বাতিল করছেন তা লিখুন।']);

        return response()->json($this->shape($this->service->reject($vacateRequest, Auth::user(), $v['reason'])));
    }

    public function cancel(TenantVacateRequest $vacateRequest)
    {
        abort_unless($vacateRequest->requested_by === Auth::id() || $this->isAdmin(), 403);

        return response()->json($this->shape($this->service->cancel($vacateRequest, Auth::user())));
    }
}
