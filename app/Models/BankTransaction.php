<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    protected $fillable = [
        'bank_account_id', 'txn_date', 'particulars', 'debit', 'credit',
        'balance_after', 'fund_type', 'ref_type', 'ref_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'txn_date' => 'date:Y-m-d',
            'debit' => 'decimal:4',
            'credit' => 'decimal:4',
            'balance_after' => 'decimal:4',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
