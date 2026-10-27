<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HallBooking extends Model
{
    use SoftDeletes;

    /** Statuses that hold the date: nobody else may take an overlapping slot. */
    public const ACTIVE_STATUSES = ['PENDING', 'CONFIRMED', 'COMPLETED'];

    protected $fillable = [
        'booking_no', 'community_hall_id', 'flat_id', 'customer_name', 'customer_mobile', 'event_type',
        'guest_count', 'booking_date', 'slot', 'start_time', 'end_time', 'rent_amount', 'extra_charge',
        'discount', 'total_amount', 'security_deposit', 'status', 'notes', 'status_reason',
        'requested_by', 'approved_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date:Y-m-d',
            'rent_amount' => 'decimal:4',
            'extra_charge' => 'decimal:4',
            'discount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'security_deposit' => 'decimal:4',
            'decided_at' => 'datetime',
        ];
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(CommunityHall::class, 'community_hall_id');
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(HallBookingPayment::class);
    }

    /** Net money held for a payment type, i.e. received minus given back. */
    public function netPaid(string $type): string
    {
        // Queried each time (not the loaded relation): it is read again right after a payment is written.
        $in = (string) ($this->payments()->where('type', $type)->sum('amount') ?: 0);
        $out = (string) ($this->payments()->where('type', $type.'_REFUND')->sum('amount') ?: 0);

        return bcsub($in, $out, 4);
    }
}
