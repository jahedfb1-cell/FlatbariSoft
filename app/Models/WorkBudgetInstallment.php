<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkBudgetInstallment extends Model
{
    protected $fillable = ['work_budget_id', 'seq', 'due_date', 'percent'];

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d', 'percent' => 'decimal:4'];
    }
}
