<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConstructionDue extends Model
{
    protected $fillable = ['flat_id', 'category', 'source_type', 'resolution_id', 'total_amount', 'paid_amount', 'balance'];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:4',
            'paid_amount' => 'decimal:4',
            'balance' => 'decimal:4',
        ];
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(DueInstallment::class);
    }
}
