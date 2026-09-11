<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    protected $fillable = [
        'billing_period_id', 'flat_id', 'service_charge', 'gas_amount', 'other_charges',
        'late_fee', 'previous_due', 'total_payable', 'paid_amount', 'due_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'service_charge' => 'decimal:4',
            'gas_amount' => 'decimal:4',
            'other_charges' => 'decimal:4',
            'late_fee' => 'decimal:4',
            'previous_due' => 'decimal:4',
            'total_payable' => 'decimal:4',
            'paid_amount' => 'decimal:4',
            'due_date' => 'date:Y-m-d',
        ];
    }

    public function billingPeriod(): BelongsTo
    {
        return $this->belongsTo(BillingPeriod::class);
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
