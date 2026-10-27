<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetServiceLog extends Model
{
    protected $fillable = ['asset_id', 'service_date', 'description', 'cost', 'technician'];

    protected function casts(): array
    {
        return [
            'service_date' => 'date:Y-m-d',
            'cost' => 'decimal:4',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
