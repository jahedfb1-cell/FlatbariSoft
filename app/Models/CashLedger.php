<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashLedger extends Model
{
    protected $table = 'cash_ledger';

    protected $fillable = [
        'txn_date', 'particulars', 'in_amount', 'out_amount',
        'balance_after', 'fund_type', 'ref_type', 'ref_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'txn_date' => 'date:Y-m-d',
            'in_amount' => 'decimal:4',
            'out_amount' => 'decimal:4',
            'balance_after' => 'decimal:4',
        ];
    }
}
