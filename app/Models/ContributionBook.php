<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContributionBook extends Model
{
    protected $fillable = ['code', 'name_bn', 'name_en', 'default_target', 'credit_account_code', 'sort_order'];

    protected function casts(): array
    {
        return ['default_target' => 'decimal:4'];
    }

    public function heads(): HasMany
    {
        return $this->hasMany(ContributionHead::class);
    }

    public function hasTarget(): bool
    {
        return bccomp((string) $this->default_target, '0', 4) > 0;
    }
}
