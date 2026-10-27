<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    protected $fillable = ['title', 'meeting_date', 'venue', 'agenda', 'minutes', 'status'];

    protected function casts(): array
    {
        return ['meeting_date' => 'date:Y-m-d'];
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(Resolution::class);
    }
}
