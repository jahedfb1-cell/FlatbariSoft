<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Resolution;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function index()
    {
        return Meeting::with('resolutions')->orderByDesc('meeting_date')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'meeting_date' => 'required|date',
            'venue' => 'nullable|string|max:255',
            'agenda' => 'nullable|string',
        ]);

        $meeting = Meeting::create(array_merge($data, ['status' => 'SCHEDULED']));

        return response()->json($meeting, 201);
    }

    public function addResolution(Request $request, Meeting $meeting)
    {
        $data = $request->validate([
            'description' => 'required|string',
            'decision' => 'nullable|string',
        ]);

        $resolutionNo = 'RES-'.$meeting->id.'-'.str_pad((string) ($meeting->resolutions()->count() + 1), 3, '0', STR_PAD_LEFT);

        $resolution = $meeting->resolutions()->create(array_merge($data, [
            'resolution_no' => $resolutionNo,
            'status' => 'PENDING',
        ]));

        return response()->json($resolution, 201);
    }
}
