<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = ['fiscal_year', 'expense_head_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }
}
