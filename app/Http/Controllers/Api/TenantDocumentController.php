<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantDocumentRequest;
use App\Http\Resources\TenantDocumentResource;
use App\Models\Tenant;
use App\Models\TenantDocument;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Auth;

class TenantDocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function index(Tenant $tenant)
    {
        return TenantDocumentResource::collection(
            $tenant->documents()->latest()->get(),
        );
    }

    public function store(StoreTenantDocumentRequest $request, Tenant $tenant)
    {
        $path = $this->documents->store($request->file('file'), "tenant-documents/{$tenant->id}");

        $document = TenantDocument::create([
            'tenant_id' => $tenant->id,
            'document_type' => $request->validated('document_type'),
            'side' => $request->validated('side'),
            'file_path' => $path,
            'uploaded_at' => now(),
            'uploaded_by' => Auth::id(),
        ]);

        return new TenantDocumentResource($document);
    }

    public function destroy(TenantDocument $tenantDocument)
    {
        // Soft delete only — see OwnerDocumentController::destroy for why
        // the file itself isn't unlinked here.
        $tenantDocument->delete();

        return response()->json(['message' => 'ডকুমেন্ট মুছে ফেলা হয়েছে।']);
    }
}
