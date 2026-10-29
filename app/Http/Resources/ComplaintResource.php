<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A complaint as the office sees it: which flat, who lives there, who is on it, how it ended. */
class ComplaintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $names = $this->flat?->occupantNames() ?? ['owner' => null, 'tenant' => null];

        return [
            'id' => $this->id,
            'flat_id' => $this->flat_id,
            'flat_no' => $this->flat?->flat_no,
            'owner' => $names['owner'],
            'tenant' => $names['tenant'],
            'category' => $this->category,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->assignee?->name,
            'resolution_note' => $this->resolution_note,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolved_by' => $this->resolvedBy?->displayName(),
            'created_at' => $this->created_at?->toIso8601String(),
            'rating' => $this->rating,
        ];
    }
}
