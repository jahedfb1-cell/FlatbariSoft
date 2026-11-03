<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffKycRequest;
use App\Http\Requests\StoreStaffDocumentRequest;
use App\Models\Staff;
use App\Models\StaffDocument;
use App\Services\StaffKycService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StaffKycController extends Controller
{
    public function __construct(private readonly StaffKycService $service) {}

    public function show(Staff $staff)
    {
        return response()->json($this->service->present($staff));
    }

    public function update(StaffKycRequest $request, Staff $staff)
    {
        return response()->json($this->service->present($this->service->save($staff, $request->validated())));
    }

    public function upload(StoreStaffDocumentRequest $request, Staff $staff)
    {
        $this->service->attach($staff, $request->validated('type'), $request->file('files'), Auth::id());

        return response()->json($this->service->present($staff->fresh()), 201);
    }

    /** Opens a paper in the browser; only people who may see staff reach this. */
    public function file(StaffDocument $document)
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404, 'ফাইলটি পাওয়া যায়নি।');

        return Storage::disk('local')->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($document->original_name).'"',
        ]);
    }

    public function destroy(StaffDocument $document)
    {
        $staff = $document->staff;
        $this->service->remove($document);

        return response()->json($this->service->present($staff));
    }
}
