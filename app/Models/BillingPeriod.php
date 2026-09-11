<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPeriod extends Model
{
    protected $fillable = ['year', 'month', 'status', 'generated_by', 'locked_at'];

    protected function casts(): array
    {
        return ['locked_at' => 'datetime'];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }
}
