<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantVacateRequest extends Model
{
    protected $fillable = ['flat_id', 'tenant_id', 'requested_by', 'end_date', 'note', 'status', 'decided_by', 'decided_at', 'reject_reason'];

    protected function casts(): array
    {
        return ['end_date' => 'date:Y-m-d', 'decided_at' => 'datetime'];
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
