<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FlatParty extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'flat_id',
        'person_id',
        'role',
        'from_date',
        'to_date',
        'is_billing_contact',
    ];

    protected function casts(): array
    {
        return [
            // Explicit Y-m-d format: Eloquent's bare 'date' cast otherwise
            // serializes writes as full datetimes ("...00:00:00"), which a
            // real MySQL DATE column silently truncates but SQLite (tests)
            // stores verbatim — same code, different DB, different bytes.
            'from_date' => 'date:Y-m-d',
            'to_date' => 'date:Y-m-d',
            'is_billing_contact' => 'boolean',
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

    public function scopeCurrent($query)
    {
        return $query->whereNull('to_date');
    }
}
