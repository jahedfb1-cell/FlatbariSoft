<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityHall extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'building_id', 'name', 'capacity', 'description', 'security_deposit', 'advance_percent',
        'free_cancel_days', 'late_cancel_refund_percent', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'security_deposit' => 'decimal:4',
            'advance_percent' => 'decimal:2',
            'late_cancel_refund_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(HallRate::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(HallBooking::class);
    }
}
