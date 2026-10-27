<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GasPurchase extends Model
{
    use SoftDeletes;

    protected $fillable = ['date', 'supplier_name', 'quantity_kg', 'unit_price', 'transport_cost', 'total_cost', 'expense_id', 'invoice_file'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'quantity_kg' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'transport_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
