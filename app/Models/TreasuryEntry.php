<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreasuryEntry extends Model
{
    protected $fillable = [
        'kind', 'fund', 'to_fund', 'medium', 'amount', 'entry_date', 'notes', 'receipt_no',
        'reverses_id', 'journal_entry_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'entry_date' => 'date:Y-m-d'];
    }
}
