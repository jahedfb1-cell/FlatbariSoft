<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GasConsumer extends Model
{
    protected $fillable = ['flat_id', 'name', 'meter_no', 'is_internal', 'active'];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(GasMeterReading::class);
    }
}
