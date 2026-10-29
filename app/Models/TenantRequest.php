<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantRequest extends Model
{
    protected $fillable = [
        'flat_id', 'requested_by', 'name_bn', 'name_en', 'mobile', 'nid', 'start_date', 'monthly_rent', 'security_deposit',
        'occupation', 'family_members_count', 'note', 'status', 'decided_by', 'decided_at', 'reject_reason', 'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'monthly_rent' => 'decimal:4',
            'security_deposit' => 'decimal:4',
            'decided_at' => 'datetime',
        ];
    }

    public function flat(): BelongsTo
    {
        return $this->belongsTo(Flat::class);
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
