<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Staff records include monthly_salary — unlike flats/persons, this is not
 * given to Manager (view-only elsewhere): salary is sensitive information,
 * so only Admin (and super_admin, which bypasses all checks) can see or
 * manage the staff registry.
 */
class StaffPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = ['staff.view', 'staff.manage'];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::where('name', 'admin')->first()?->givePermissionTo($permissions);
    }
}
