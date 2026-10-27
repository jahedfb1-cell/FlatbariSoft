<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingCharge extends Model
{
    protected $fillable = ['parking_allocation_id', 'year', 'month', 'amount', 'due_date'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'due_date' => 'date:Y-m-d'];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(ParkingAllocation::class, 'parking_allocation_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ParkingPayment::class);
    }

    /** Derived, never stored (NFR-08): what has been received for this charge so far. */
    public function paid(): string
    {
        return (string) ($this->payments()->sum('amount') ?: 0);
    }

    public function due(): string
    {
        return bcsub((string) $this->amount, bcadd($this->paid(), '0', 4), 4);
    }
}
