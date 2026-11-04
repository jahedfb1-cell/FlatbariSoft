<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAdvance extends Model
{
    protected $fillable = ['staff_id', 'amount', 'monthly_deduction', 'given_date', 'note', 'expense_id', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'monthly_deduction' => 'decimal:4', 'given_date' => 'date:Y-m-d'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
