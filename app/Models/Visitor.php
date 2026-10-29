<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visitor extends Model
{
    protected $fillable = ['flat_id', 'visitor_name', 'mobile', 'purpose', 'vehicle_no', 'note', 'in_at', 'out_at', 'logged_by'];

    protected function casts(): array
    {
        return ['in_at' => 'datetime', 'out_at' => 'datetime'];
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }
}
