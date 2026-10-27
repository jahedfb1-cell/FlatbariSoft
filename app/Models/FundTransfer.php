<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundTransfer extends Model
{
    protected $fillable = [
        'from_fund', 'to_fund', 'amount', 'reason', 'resolution_id',
        'transfer_date', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'transfer_date' => 'date:Y-m-d',
        ];
    }
}
