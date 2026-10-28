<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeetingRequest;
use App\Http\Requests\StoreResolutionAttachmentRequest;
use App\Http\Requests\StoreResolutionRequest;
use App\Models\Meeting;
use App\Models\Resolution;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
        $data = $request->safe()->except('attachment');

        $resolutionNo = 'RES-'.$meeting->id.'-'.str_pad((string) ($meeting->resolutions()->count() + 1), 3, '0', STR_PAD_LEFT);

        $resolution = $meeting->resolutions()->create(array_merge($data, [
            'resolution_no' => $resolutionNo,
            'status' => 'PENDING',
        ], $request->hasFile('attachment') ? $this->saveFile($request->file('attachment')) : []));

        return response()->json($resolution, 201);
    }

    /** Attach a PDF/photo to a resolution already recorded, replacing the earlier file. */
    public function attach(StoreResolutionAttachmentRequest $request, Resolution $resolution)
    {
        $old = $resolution->attachment_path;
        $resolution->update($this->saveFile($request->file('attachment')));
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return response()->json($resolution->fresh());
    }

    /** The stored file itself - never a public URL; only people who may view meetings can fetch it. */
    public function attachment(Resolution $resolution)
    {
        abort_unless($resolution->attachment_path && Storage::disk('local')->exists($resolution->attachment_path), 404, 'এই সিদ্ধান্তের সাথে কোনো ফাইল নেই।');

        return Storage::disk('local')->response($resolution->attachment_path, $resolution->attachment_name, [
            'Content-Type' => $resolution->attachment_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($resolution->attachment_name ?: 'resolution').'"',
        ]);
    }

    /** @return array{attachment_path: string, attachment_name: string, attachment_mime: string} */
    private function saveFile(UploadedFile $file): array
    {
        return [
            'attachment_path' => $file->store('resolutions', 'local'),
            'attachment_name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'attachment_mime' => $file->getMimeType() ?: 'application/octet-stream',
        ];
    }
}
