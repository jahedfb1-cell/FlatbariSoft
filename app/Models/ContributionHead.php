<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContributionHead extends Model
{
    protected $fillable = ['code', 'name_bn', 'name_en', 'counts_toward_target', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['counts_toward_target' => 'boolean', 'is_active' => 'boolean'];
    }
}
