<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeetingRequest;
use App\Http\Requests\StoreResolutionRequest;
use App\Models\Meeting;

class MeetingController extends Controller
{
    public function index()
    {
        return Meeting::with('resolutions')->orderByDesc('meeting_date')->get();
    }

    public function store(StoreMeetingRequest $request)
    {
        $meeting = Meeting::create(array_merge($request->validated(), ['status' => 'SCHEDULED']));

        return response()->json($meeting, 201);
    }

    public function addResolution(StoreResolutionRequest $request, Meeting $meeting)
    {
        $data = $request->validated();

        $resolutionNo = 'RES-'.$meeting->id.'-'.str_pad((string) ($meeting->resolutions()->count() + 1), 3, '0', STR_PAD_LEFT);

        $resolution = $meeting->resolutions()->create(array_merge($data, [
            'resolution_no' => $resolutionNo,
            'status' => 'PENDING',
        ]));

        return response()->json($resolution, 201);
    }
}
