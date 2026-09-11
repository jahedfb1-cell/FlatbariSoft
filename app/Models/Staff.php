<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'designation',
        'mobile',
        'nid',
        'join_date',
        'monthly_salary',
        'photo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date:Y-m-d',
            'monthly_salary' => 'decimal:4',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
