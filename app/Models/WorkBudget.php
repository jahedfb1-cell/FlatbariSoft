<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkBudget extends Model
{
    use SoftDeletes;

    public const STATUSES = ['PLANNED', 'ONGOING', 'DONE', 'CANCELLED'];

    protected $fillable = [
        'contribution_book_id', 'title', 'description', 'estimated_cost', 'start_date', 'end_date', 'status', 'split_method', 'resolution_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['estimated_cost' => 'decimal:4', 'start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(ContributionBook::class, 'contribution_book_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['PLANNED', 'ONGOING'], true);
    }
}
