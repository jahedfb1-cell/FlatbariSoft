<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'flat_id', 'bill_id', 'amount', 'payment_date', 'method', 'reference_no',
        'receipt_no', 'received_by', 'approved_by', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'payment_date' => 'date:Y-m-d',
        ];
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
