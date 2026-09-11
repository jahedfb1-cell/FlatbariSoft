<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        return Complaint::with('flat')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'flat_id' => 'required|exists:flats,id',
            'category' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => ['nullable', Rule::in(['LOW', 'MEDIUM', 'HIGH'])],
        ]);

        $complaint = Complaint::create(array_merge($data, ['status' => 'OPEN']));

        return response()->json($complaint, 201);
    }

    public function updateStatus(Request $request, Complaint $complaint)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'])],
            'assigned_to' => 'nullable|exists:staff,id',
        ]);

        if ($data['status'] === 'RESOLVED' && $complaint->status !== 'RESOLVED') {
            $data['resolved_at'] = now();
        }

        $complaint->update($data);

        return response()->json($complaint->fresh());
    }
}
