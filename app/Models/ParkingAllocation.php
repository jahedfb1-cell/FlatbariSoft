<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingAllocation extends Model
{
    protected $fillable = ['parking_slot_id', 'flat_id', 'person_id', 'vehicle_no', 'from_date', 'to_date', 'monthly_fee', 'notes'];

    protected function casts(): array
    {
        return ['from_date' => 'date:Y-m-d', 'to_date' => 'date:Y-m-d', 'monthly_fee' => 'decimal:4'];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ParkingSlot::class, 'parking_slot_id');
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(ParkingCharge::class);
    }
}
