<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bank_name', 'branch', 'account_no', 'account_title', 'mandate_rule',
        'opening_balance', 'current_balance',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:4',
            'current_balance' => 'decimal:4',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
