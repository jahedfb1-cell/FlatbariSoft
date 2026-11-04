<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes;

    /**
     * An active login by what the person typed: an e-mail address, or a Bangladeshi mobile number in any common
     * writing (01712345678, 8801712345678, +8801712345678, with spaces or dashes).
     */
    public static function findActiveByIdentifier(string $identifier): ?self
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }
        $query = self::where('status', 'ACTIVE');
        if (str_contains($identifier, '@')) {
            return $query->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])->first();
        }
        $digits = preg_replace('/\D+/', '', $identifier);
        if (str_starts_with($digits, '880')) {
            $digits = '0'.substr($digits, 3);
        }

        return $query->where('mobile', $digits)->first();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'person_id',
        'staff_id',
        'country_code',
        'mobile',
        'email',
        'password',
        'role',
        'status',
        'last_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_login' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** A name for screens and logs: the person's name, or the mobile number for a login with no person (office staff). */
    public function displayName(): string
    {
        return $this->person?->name_bn ?: $this->person?->name_en ?: (string) $this->mobile;
    }

    public function person(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function staff(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
