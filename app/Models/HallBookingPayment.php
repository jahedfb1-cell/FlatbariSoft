<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HallBookingPayment extends Model
{
    protected $fillable = [
        'hall_booking_id', 'type', 'amount', 'method', 'paid_on', 'reference_no',
        'receipt_no', 'journal_entry_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'paid_on' => 'date:Y-m-d',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HallBooking::class, 'hall_booking_id');
    }
}
