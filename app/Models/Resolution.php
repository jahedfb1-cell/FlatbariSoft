<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resolution extends Model
{
    protected $fillable = [
        'meeting_id', 'resolution_no', 'description', 'decision', 'status',
        'attachment_path', 'attachment_name', 'attachment_mime',
    ];

    // the storage path never leaves the server; the file is fetched through an authorised endpoint
    protected $hidden = ['attachment_path'];

    protected $appends = ['has_attachment'];

    public function getHasAttachmentAttribute(): bool
    {
        return (bool) $this->attachment_path;
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
