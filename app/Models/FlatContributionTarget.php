<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlatContributionTarget extends Model
{
    protected $fillable = ['flat_id', 'target_amount', 'note'];

    protected function casts(): array
    {
        return ['target_amount' => 'decimal:4'];
    }
}
