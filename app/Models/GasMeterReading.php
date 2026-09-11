<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GasMeterReading extends Model
{
    protected $fillable = [
        'gas_consumer_id', 'billing_period_id', 'previous_reading', 'current_reading',
        'units_used', 'reading_date', 'entered_by', 'photo',
    ];

    protected function casts(): array
    {
        return [
            'previous_reading' => 'decimal:4',
            'current_reading' => 'decimal:4',
            'units_used' => 'decimal:4',
            'reading_date' => 'date:Y-m-d',
        ];
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(GasConsumer::class, 'gas_consumer_id');
    }

    public function billingPeriod(): BelongsTo
    {
        return $this->belongsTo(BillingPeriod::class);
    }
}
