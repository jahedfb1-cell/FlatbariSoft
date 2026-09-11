<?php

namespace App\Services;

use App\Models\Person;

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
}
