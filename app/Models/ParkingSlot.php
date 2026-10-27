<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParkingSlot extends Model
{
    use SoftDeletes;

    protected $fillable = ['building_id', 'code', 'zone', 'vehicle_type', 'monthly_fee', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['monthly_fee' => 'decimal:4', 'is_active' => 'boolean'];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ParkingAllocation::class);
    }

    /** The current holder: the one allocation that has not been released. */
    public function currentAllocation(): HasOne
    {
        return $this->hasOne(ParkingAllocation::class)->whereNull('to_date')->latestOfMany();
    }
}
