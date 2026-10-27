<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A person (owner or tenant) is looked up by mobile number before a new
 * record is created — the same real individual should never end up with
 * two `persons` rows just because they were added from two different
 * "assign owner"/"assign tenant" forms.
 */
class PersonService
{
    public function findOrCreate(array $data): Person
    {
        if (! empty($data['mobile'])) {
            $existing = Person::where('mobile', $data['mobile'])->first();

            if ($existing) {
                return $existing;
            }
        }

        return Person::create(array_merge([
            'country_code' => '+880',
            'preferred_channel' => 'SMS',
        ], $data));
    }

    /**
     * Assigning someone as an owner/tenant is also the only moment a real
     * person needs a login — without this, Phase 4.4's owner portal has no
     * way for an actual owner/tenant to ever reach it. The password is a
     * random unusable placeholder: login is mobile+OTP (Phase 0.4), never
     * password, so nothing needs to hand this value to anyone.
     */
    public function ensureUserAccount(Person $person, string $role): User
    {
        $user = User::where('person_id', $person->id)->first();

        if ($user) {
            return $user;
        }

        $user = User::create([
            'person_id' => $person->id,
            'mobile' => $person->mobile,
            'password' => Hash::make(Str::random(40)),
            'role' => $role,
            'status' => 'ACTIVE',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
