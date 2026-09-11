<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'flat_id',
        'person_id',
        'occupation',
        'family_members_count',
        'start_date',
        'end_date',
        'monthly_rent',
        'security_deposit',
        'status',
    ];

    protected function casts(): array
    {
        return [
            // Explicit Y-m-d format — see FlatParty::casts() for why.
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'monthly_rent' => 'decimal:4',
            'security_deposit' => 'decimal:4',
        ];
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
