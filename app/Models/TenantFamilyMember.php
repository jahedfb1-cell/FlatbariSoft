<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantFamilyMember extends Model
{
    protected $fillable = ['tenant_id', 'name', 'relation', 'age', 'nid', 'mobile', 'occupation'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
