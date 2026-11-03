<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A paper kept for a staff member: CV, NID copy, photo, certificate. Stored privately and only served to people allowed to see staff. */
class StaffDocument extends Model
{
    protected $fillable = ['staff_id', 'type', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    protected $hidden = ['path'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
