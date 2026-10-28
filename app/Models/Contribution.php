<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contribution extends Model
{
    protected $fillable = [
        'flat_id', 'contribution_head_id', 'amount', 'received_on', 'method', 'reference_no',
        'receipt_no', 'reverses_id', 'journal_entry_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'received_on' => 'date:Y-m-d'];
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(ContributionHead::class, 'contribution_head_id');
    }
}
