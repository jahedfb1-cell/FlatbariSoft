<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConstructionMaterialPurchase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'date', 'item_name', 'quantity', 'unit', 'unit_rate',
        'supplier_name', 'transport_cost', 'total_cost', 'invoice_file', 'expense_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'quantity' => 'decimal:4',
            'unit_rate' => 'decimal:4',
            'transport_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
