<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GasPurchase extends Model
{
    use SoftDeletes;

    protected $fillable = ['date', 'supplier_name', 'quantity_kg', 'unit_price', 'transport_cost', 'total_cost', 'invoice_file'];

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
}
