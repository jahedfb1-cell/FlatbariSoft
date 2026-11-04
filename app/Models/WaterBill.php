<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaterBill extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bill_year', 'bill_month', 'account_no', 'bill_no', 'previous_reading', 'current_reading', 'consumption', 'water_charge',
        'sewerage_charge', 'vat', 'other_charge', 'late_fee', 'total', 'due_date', 'notes', 'expense_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'previous_reading' => 'decimal:4', 'current_reading' => 'decimal:4', 'consumption' => 'decimal:4',
            'water_charge' => 'decimal:4', 'sewerage_charge' => 'decimal:4', 'vat' => 'decimal:4', 'other_charge' => 'decimal:4',
            'late_fee' => 'decimal:4', 'total' => 'decimal:4', 'due_date' => 'date:Y-m-d',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
