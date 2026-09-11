<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DueInstallment extends Model
{
    protected $fillable = ['construction_due_id', 'installment_no', 'due_date', 'amount', 'paid_amount', 'status'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'amount' => 'decimal:4',
            'paid_amount' => 'decimal:4',
        ];
    }

    public function constructionDue(): BelongsTo
    {
        return $this->belongsTo(ConstructionDue::class);
    }
}
