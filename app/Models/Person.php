<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    // Eloquent's auto-pluralized default would be "people" (same irregular
    // plural that trips up migration foreign keys) — the table is "persons".
    protected $table = 'persons';

    protected $fillable = [
        'name_bn',
        'name_en',
        'country_code',
        'mobile',
        'alt_mobile',
        'nid',
        'email',
        'address',
        'photo',
        'preferred_channel',
    ];

    public function flatParties(): HasMany
    {
        return $this->hasMany(FlatParty::class);
    }

    public function tenancies(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class);
    }

    public function ownerDocuments(): HasMany
    {
        return $this->hasMany(OwnerDocument::class);
    }
}
