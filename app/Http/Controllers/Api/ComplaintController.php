<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplaintRequest;
use App\Http\Requests\UpdateComplaintStatusRequest;
use App\Models\Complaint;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        return Complaint::with('flat')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->get();
    }

    public function store(StoreComplaintRequest $request)
    {
        $complaint = Complaint::create(array_merge($request->validated(), ['status' => 'OPEN']));

        return response()->json($complaint, 201);
    }

    public function updateStatus(UpdateComplaintStatusRequest $request, Complaint $complaint)
    {
        $data = $request->validated();

        if ($data['status'] === 'RESOLVED' && $complaint->status !== 'RESOLVED') {
            $data['resolved_at'] = now();
        }

        $complaint->update($data);

        return response()->json($complaint->fresh());
    }
}
