<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $fillable = ['event_key', 'body_bn', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
