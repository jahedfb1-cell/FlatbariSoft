<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Asset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'category', 'purchase_date', 'purchase_cost', 'supplier_name', 'supplier_mobile',
        'warranty_expiry', 'amc_expiry', 'next_service_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date:Y-m-d',
            'purchase_cost' => 'decimal:4',
            'warranty_expiry' => 'date:Y-m-d',
            'amc_expiry' => 'date:Y-m-d',
            'next_service_date' => 'date:Y-m-d',
        ];
    }

    public function serviceLogs(): HasMany
    {
        return $this->hasMany(AssetServiceLog::class);
    }

    public function isServiceOverdue(): bool
    {
        return $this->next_service_date !== null && $this->next_service_date->lt(Carbon::today());
    }
}
