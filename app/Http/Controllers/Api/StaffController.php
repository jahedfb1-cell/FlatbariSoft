<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $staff = Staff::when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return StaffResource::collection($staff);
    }

    public function store(StoreStaffRequest $request)
    {
        $staff = Staff::create(array_merge(['status' => 'ACTIVE'], $request->validated()));

        return new StaffResource($staff);
    }

    public function show(Staff $staff)
    {
        return new StaffResource($staff);
    }

    public function update(UpdateStaffRequest $request, Staff $staff)
    {
        $staff->update($request->validated());

        return new StaffResource($staff);
    }

    public function destroy(Staff $staff)
    {
        $staff->delete();

        return response()->json(['message' => 'স্টাফ মুছে ফেলা হয়েছে।']);
    }
}
