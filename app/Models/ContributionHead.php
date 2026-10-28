<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributionHead extends Model
{
    protected $fillable = ['contribution_book_id', 'code', 'name_bn', 'name_en', 'counts_toward_target', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['counts_toward_target' => 'boolean', 'is_active' => 'boolean'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(ContributionBook::class, 'contribution_book_id');
    }
}
