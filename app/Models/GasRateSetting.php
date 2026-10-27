<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GasRateSetting extends Model
{
    protected $fillable = ['effective_from', 'method', 'rate', 'm3_per_kg', 'system_loss_percent', 'minimum_charge'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date:Y-m-d',
            'rate' => 'decimal:4',
            'm3_per_kg' => 'decimal:4',
            'system_loss_percent' => 'decimal:4',
            'minimum_charge' => 'decimal:4',
        ];
    }
}
