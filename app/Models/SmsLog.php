<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = ['mobile', 'message', 'event_key', 'gateway', 'status', 'cost', 'sent_at'];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:4',
            'sent_at' => 'datetime',
        ];
    }
}
