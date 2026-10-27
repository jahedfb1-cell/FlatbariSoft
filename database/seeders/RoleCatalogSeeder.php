<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Makes sure every shipped role and the user/role-management permissions exist. Idempotent and safe on a
 * database that already has data: a role's permissions are filled in only when the role is first created,
 * so later edits made in the Roles screen are never overwritten by a re-run.
 */
class RoleCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['user.view', 'user.manage', 'role.manage', 'hall.approve', 'hall.payments'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // admin always gets the newest permissions (it is the "can do everything" office role)
        Role::where('name', 'admin')->first()?->givePermissionTo(['user.view', 'user.manage', 'role.manage', 'hall.approve', 'hall.payments']);

        // the building manager is the one who takes cash at the hall
        Role::where('name', 'manager')->first()?->givePermissionTo(['hall.payments']);

        foreach (config('roles.defaults') as $name => $permissions) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if ($role->wasRecentlyCreated) {
                $role->syncPermissions(array_filter($permissions, fn ($p) => Permission::where('name', $p)->exists()));
            }
        }
    }
}
