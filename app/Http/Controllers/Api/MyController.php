<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BillResource;
use App\Models\Bill;
use App\Models\Complaint;
use App\Models\FlatParty;
use App\Models\OwnerDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 4.4 Owner/Tenant portal — every endpoint here is scoped to the
 * logged-in user's own person_id, resolved through flat_parties. Owners
 * and tenants use the same queries (BR-16/0.5 decision: a tenant is a
 * person too), never a raw flat_id/person_id the client supplies.
 */
class MyController extends Controller
{
    private function myFlatIds(): array
    {
        $personId = Auth::user()->person_id;
        if (! $personId) {
            return [];
        }

        return FlatParty::where('person_id', $personId)->whereNull('to_date')->pluck('flat_id')->all();
    }

    public function flats()
    {
        $flatIds = $this->myFlatIds();

        return \App\Models\Flat::whereIn('id', $flatIds)->get();
    }

    public function bills(Request $request)
    {
        $flatIds = $this->myFlatIds();

        $bills = Bill::whereIn('flat_id', $flatIds)
            ->with('flat')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->get();

        return BillResource::collection($bills);
    }

    public function documents()
    {
        $personId = Auth::user()->person_id;

        return OwnerDocument::where('person_id', $personId)->get();
    }

    public function complaints()
    {
        $flatIds = $this->myFlatIds();

        return Complaint::whereIn('flat_id', $flatIds)->latest('id')->get();
    }

    public function storeComplaint(Request $request)
    {
        $flatIds = $this->myFlatIds();

        $data = $request->validate([
            'flat_id' => 'required|in:'.implode(',', $flatIds ?: [0]),
            'category' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        $complaint = Complaint::create(array_merge($data, ['status' => 'OPEN', 'priority' => 'MEDIUM']));

        return response()->json($complaint, 201);
    }
}
