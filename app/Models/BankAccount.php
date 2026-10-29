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
        'holder_1_name', 'holder_1_phone', 'holder_2_name', 'holder_2_phone', 'holder_3_name', 'holder_3_phone', 'sms_phone',
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
