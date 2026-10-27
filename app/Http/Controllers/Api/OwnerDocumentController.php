<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOwnerDocumentRequest;
use App\Http\Resources\OwnerDocumentResource;
use App\Models\OwnerDocument;
use App\Models\Person;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Auth;

class OwnerDocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function index(Person $person)
    {
        return OwnerDocumentResource::collection(
            $person->ownerDocuments()->latest()->get(),
        );
    }

    public function store(StoreOwnerDocumentRequest $request, Person $person)
    {
        $path = $this->documents->store($request->file('file'), "owner-documents/{$person->id}");

        $document = OwnerDocument::create([
            'person_id' => $person->id,
            'flat_id' => $request->validated('flat_id'),
            'document_type' => $request->validated('document_type'),
            'side' => $request->validated('side'),
            'document_year' => $request->validated('document_year'),
            'issued_date' => $request->validated('issued_date'),
            'expiry_date' => $request->validated('expiry_date'),
            'file_path' => $path,
            'uploaded_by' => Auth::id(),
        ]);

        return new OwnerDocumentResource($document);
    }

    public function destroy(OwnerDocument $ownerDocument)
    {
        // Soft delete only — the physical file stays on disk. Unlinking it
        // now would break restoring this record later (soft delete is
        // meant to be reversible); a hard-delete/purge action, if ever
        // needed, is where the file itself would actually be removed.
        $ownerDocument->delete();

        return response()->json(['message' => 'ডকুমেন্ট মুছে ফেলা হয়েছে।']);
    }
}
