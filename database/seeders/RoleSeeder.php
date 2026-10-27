<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Seeds the five roles fixed by PRD section 2 (ইউজার রোল ও পারমিশন).
 *
 * Only the roles are seeded here — granular permissions are added
 * module-by-module as each module is built, since a permission like
 * "payment.approve" has no meaning before the payments module exists.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super_admin', 'admin', 'manager', 'flat_owner', 'tenant'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
