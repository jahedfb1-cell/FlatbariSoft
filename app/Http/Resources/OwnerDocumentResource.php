<?php

namespace App\Http\Resources;

use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnerDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'person_id' => $this->person_id,
            // Cast explicitly: right after create(), the in-memory model
            // still holds the raw request string ("4"), not the integer
            // MySQL would return on a fresh SELECT.
            'flat_id' => (int) $this->flat_id,
            'document_type' => $this->document_type,
            'document_year' => $this->document_year === null ? null : (int) $this->document_year,
            'issued_date' => $this->issued_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'file_url' => app(DocumentService::class)->url($this->file_path),
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at,
        ];
    }
}
