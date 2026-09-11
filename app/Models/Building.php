<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'total_floors',
        'total_flats',
        'logo',
    ];

    public function flats(): HasMany
    {
        return $this->hasMany(Flat::class);
    }
}
