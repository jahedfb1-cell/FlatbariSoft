<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplaintRequest;
use App\Http\Requests\UpdateComplaintStatusRequest;
use App\Http\Resources\ComplaintResource;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComplaintController extends Controller
{
    private const WITH = ['flat.currentOwners.person', 'flat.currentTenant.person', 'assignee', 'resolvedBy'];

    /** Every complaint with its flat and occupants; ?status=, ?priority= and ?q= (flat no, type or wording) narrow it. */
    public function index(Request $request)
    {
        $complaints = Complaint::with(self::WITH)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->priority))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.trim((string) $request->q).'%';
                $q->where(fn ($w) => $w->where('category', 'like', $term)->orWhere('description', 'like', $term)
                    ->orWhereHas('flat', fn ($f) => $f->where('flat_no', 'like', $term)));
            })
            // open work first (high priority at the top), finished ones after, newest first within each
            ->orderByRaw("CASE WHEN status IN ('OPEN','IN_PROGRESS') THEN 0 ELSE 1 END")
            ->orderByRaw("CASE priority WHEN 'HIGH' THEN 0 WHEN 'MEDIUM' THEN 1 ELSE 2 END")
            ->latest('id')
            ->get();

        return response()->json([
            'counts' => Complaint::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'complaints' => ComplaintResource::collection($complaints)->resolve($request),
        ]);
    }

    public function store(StoreComplaintRequest $request)
    {
        $complaint = Complaint::create(array_merge($request->validated(), ['status' => 'OPEN']));

        return response()->json((new ComplaintResource($complaint->load(self::WITH)))->resolve($request), 201);
    }

    public function updateStatus(UpdateComplaintStatusRequest $request, Complaint $complaint)
    {
        $data = $request->validated();
        $finished = in_array($data['status'], ['RESOLVED', 'CLOSED'], true);

        if ($data['status'] === 'RESOLVED' && $complaint->status !== 'RESOLVED') {
            $data['resolved_at'] = now();
            $data['resolved_by'] = Auth::id();
        }
        if (! $finished) {
            // re-opened: it is no longer finished, so forget who finished it and when
            $data['resolved_at'] = null;
            $data['resolved_by'] = null;
        }

        $complaint->update($data);

        return response()->json((new ComplaintResource($complaint->fresh(self::WITH)))->resolve($request));
    }
}
