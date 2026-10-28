<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContributionSetting extends Model
{
    protected $fillable = ['default_target'];

    protected function casts(): array
    {
        return ['default_target' => 'decimal:4'];
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->first() ?? static::create(['default_target' => 0]);
    }
}
