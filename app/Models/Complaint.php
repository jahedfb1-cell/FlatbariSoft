<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    protected $fillable = [
        'flat_id', 'category', 'description', 'voice_note', 'images', 'priority',
        'status', 'assigned_to', 'resolution_note', 'resolved_by', 'resolved_at', 'rating',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_to');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
