<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Who can log in, and as what. A user is always tied to a person (so a name and mobile exist); roles are
 * spatie roles and a person may hold several (a committee secretary is usually also a flat owner).
 *
 * Guards that keep the system from locking itself out or being taken over:
 *  - super_admin can never be granted or removed here;
 *  - you cannot deactivate yourself or take your own admin access away;
 *  - deactivating or resetting a login revokes its API tokens at once.
 */
class UserManagementService
{
    /** The label shown for a user with several roles: the most powerful one wins. */
    private const ROLE_PRIORITY = [
        'super_admin', 'admin', 'committee_president', 'committee_secretary', 'committee_treasurer',
        'manager', 'committee_member', 'flat_owner', 'tenant',
    ];

    public function __construct(private readonly PersonService $people) {}

    /**
     * $data: roles[], mobile, name_bn, email?, password, person_id? (an existing person; otherwise one is
     * found by mobile or created).
     */
    public function create(array $data, User $actor): User
    {
        $roles = $this->assignableRoles($data['roles'] ?? []);

        return DB::transaction(function () use ($data, $roles) {
            $person = ! empty($data['person_id'])
                ? Person::findOrFail($data['person_id'])
                : $this->people->findOrCreate([
                    'name_bn' => $data['name_bn'],
                    'mobile' => $data['mobile'],
                    'email' => $data['email'] ?? null,
                ]);

            if (User::withTrashed()->where('person_id', $person->id)->exists()) {
                throw ValidationException::withMessages(['mobile' => 'এই ব্যক্তির ইতিমধ্যে অ্যাকাউন্ট আছে — সেটির রোল বদলান।']);
            }
            if (User::withTrashed()->where('mobile', $person->mobile)->exists()) {
                throw ValidationException::withMessages(['mobile' => 'এই মোবাইল নম্বরে ইতিমধ্যে একটি অ্যাকাউন্ট আছে।']);
            }

            $user = User::create([
                'person_id' => $person->id,
                'mobile' => $person->mobile,
                'email' => $data['email'] ?? $person->email,
                'password' => $data['password'],
                'role' => $this->primaryRole($roles),
                'status' => 'ACTIVE',
            ]);
            $user->syncRoles($roles);

            return $user->fresh();
        });
    }

    /** $data: email?, status?, roles? - only the keys present are changed. */
    public function update(User $user, array $data, User $actor): User
    {
        $this->assertNotSuperAdmin($user);

        return DB::transaction(function () use ($user, $data, $actor) {
            $isSelf = $user->is($actor);

            if (array_key_exists('roles', $data)) {
                $roles = $this->assignableRoles($data['roles']);
                if ($isSelf && ! in_array('admin', $roles, true) && $user->hasRole('admin')) {
                    throw ValidationException::withMessages(['roles' => 'নিজের অ্যাডমিন রোল সরানো যাবে না।']);
                }
                $user->syncRoles($roles);
                $user->role = $this->primaryRole($roles);
            }

            if (array_key_exists('email', $data)) {
                $user->email = $data['email'];
            }

            if (array_key_exists('status', $data) && $data['status'] !== $user->status) {
                if ($isSelf && $data['status'] === 'INACTIVE') {
                    throw ValidationException::withMessages(['status' => 'নিজের অ্যাকাউন্ট নিষ্ক্রিয় করা যাবে না।']);
                }
                $user->status = $data['status'];
                if ($data['status'] === 'INACTIVE') {
                    $user->tokens()->delete(); // a deactivated person is logged out everywhere now
                }
            }

            $user->save();

            return $user->fresh();
        });
    }

    public function resetPassword(User $user, string $password): void
    {
        $this->assertNotSuperAdmin($user);

        DB::transaction(function () use ($user, $password) {
            $user->update(['password' => $password]); // 'hashed' cast
            $user->tokens()->delete();
        });
    }

    /** Role names an admin may hand out: any existing role except super_admin. */
    private function assignableRoles(array $names): array
    {
        $names = array_values(array_unique($names));
        if ($names === []) {
            throw ValidationException::withMessages(['roles' => 'কমপক্ষে একটি রোল দিন।']);
        }
        if (in_array('super_admin', $names, true)) {
            throw ValidationException::withMessages(['roles' => 'সুপার অ্যাডমিন রোল এখান থেকে দেওয়া যায় না।']);
        }
        $known = Role::whereIn('name', $names)->pluck('name')->all();
        if (count($known) !== count($names)) {
            throw ValidationException::withMessages(['roles' => 'অজানা রোল।']);
        }

        return $names;
    }

    private function assertNotSuperAdmin(User $user): void
    {
        if ($user->hasRole('super_admin')) {
            throw ValidationException::withMessages(['user' => 'সুপার অ্যাডমিন অ্যাকাউন্ট এখান থেকে বদলানো যায় না।']);
        }
    }

    private function primaryRole(array $roles): string
    {
        foreach (self::ROLE_PRIORITY as $name) {
            if (in_array($name, $roles, true)) {
                return $name;
            }
        }

        return $roles[0]; // a custom role
    }
}
