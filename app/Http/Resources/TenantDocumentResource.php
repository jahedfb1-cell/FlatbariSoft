<?php

namespace App\Http\Resources;

use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Cast explicitly — see OwnerDocumentResource for why (multipart
            // uploads deliver every field as a string, unlike JSON requests).
            'tenant_id' => (int) $this->tenant_id,
            'document_type' => $this->document_type,
            'file_url' => app(DocumentService::class)->url($this->file_path),
            'uploaded_at' => $this->uploaded_at,
            'uploaded_by' => $this->uploaded_by,
        ];
    }
}
