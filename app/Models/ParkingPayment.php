<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingPayment extends Model
{
    protected $fillable = ['parking_charge_id', 'amount', 'method', 'paid_on', 'reference_no', 'receipt_no', 'journal_entry_id', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'paid_on' => 'date:Y-m-d'];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(ParkingCharge::class, 'parking_charge_id');
    }
}
