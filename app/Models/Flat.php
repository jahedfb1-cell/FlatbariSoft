<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Flat extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'building_id',
        'flat_no',
        'floor',
        'size_sqft',
        'occupancy_status',
        'service_charge_rate',
        'empty_flat_rate',
        'gas_meter_no',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'size_sqft' => 'decimal:2',
            'service_charge_rate' => 'decimal:4',
            'empty_flat_rate' => 'decimal:4',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function flatParties(): HasMany
    {
        return $this->hasMany(FlatParty::class);
    }

    public function currentOwners(): HasMany
    {
        return $this->flatParties()->where('role', 'OWNER')->whereNull('to_date');
    }

    public function currentTenantParty(): HasMany
    {
        return $this->flatParties()->where('role', 'TENANT')->whereNull('to_date');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function currentTenant(): HasMany
    {
        return $this->tenants()->where('status', 'ACTIVE');
    }

    /**
     * The names a list row shows for this flat: the owner, and the tenant when one is living there.
     * Needs currentOwners.person and currentTenant.person to be loaded (it never queries on its own).
     *
     * @return array{owner:?string, tenant:?string}
     */
    public function occupantNames(): array
    {
        $name = fn ($person) => $person?->name_en ?: $person?->name_bn;
        $join = fn ($rows) => $rows->map(fn ($r) => $name($r->person))->filter()->implode(', ') ?: null;

        return [
            'owner' => $this->relationLoaded('currentOwners') ? $join($this->currentOwners) : null,
            'tenant' => $this->relationLoaded('currentTenant') ? $join($this->currentTenant) : null,
        ];
    }
}
