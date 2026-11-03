<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'designation',
        'mobile',
        'nid',
        'join_date',
        'monthly_salary',
        'photo',
        'status',
        'father_name', 'mother_name', 'spouse_name', 'date_of_birth', 'gender', 'marital_status', 'blood_group', 'religion',
        'alt_mobile', 'email', 'present_address', 'permanent_address', 'emergency_name', 'emergency_phone', 'emergency_relation',
        'education', 'experience', 'reference_name', 'reference_phone', 'other_info', 'family_members', 'kyc_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date:Y-m-d',
            'monthly_salary' => 'decimal:4',
            'date_of_birth' => 'date:Y-m-d',
            'family_members' => 'array',
            'kyc_updated_at' => 'datetime',
        ];
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StaffDocument::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
