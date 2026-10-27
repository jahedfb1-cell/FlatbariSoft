<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HallRate extends Model
{
    protected $fillable = ['community_hall_id', 'slot', 'start_time', 'end_time', 'resident_rate', 'outsider_rate'];

    protected function casts(): array
    {
        return [
            'resident_rate' => 'decimal:4',
            'outsider_rate' => 'decimal:4',
        ];
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(CommunityHall::class, 'community_hall_id');
    }
}
