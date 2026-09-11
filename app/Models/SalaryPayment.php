<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPayment extends Model
{
    protected $fillable = [
        'staff_id', 'year', 'month', 'basic', 'overtime', 'bonus', 'advance_deduction',
        'net_amount', 'paid_date', 'treasury_medium', 'status', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'basic' => 'decimal:4',
            'overtime' => 'decimal:4',
            'bonus' => 'decimal:4',
            'advance_deduction' => 'decimal:4',
            'net_amount' => 'decimal:4',
            'paid_date' => 'date:Y-m-d',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
