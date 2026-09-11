<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionHead extends Model
{
    protected $fillable = [
        'code', 'name_bn', 'name_en', 'payer_type', 'charge_type', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
