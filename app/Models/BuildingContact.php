<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuildingContact extends Model
{
    protected $fillable = ['name', 'role', 'phone', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
