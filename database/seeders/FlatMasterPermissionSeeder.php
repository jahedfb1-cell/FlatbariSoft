<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for Phase 0.5 (buildings/flats/persons/tenants master data).
 *
 * super_admin bypasses all permission checks (see AppServiceProvider's
 * Gate::before), so it is never assigned permissions directly.
 *
 * PRD section 2 doesn't spell out master-data access per role, so this is
 * an inference: Admin (কমিটি) manages the registry since it's routine
 * administrative work; Manager/কেয়ারটেকার only needs to see it for
 * reference (their listed duties are meter reading, collection, expense,
 * complaints — not editing ownership records); Flat Owner/Tenant get none
 * of this — they'll get their own scoped "my flat" endpoint later.
 */
class FlatMasterPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'building.view', 'building.update',
            'flat.view', 'flat.create', 'flat.update', 'flat.delete',
            'person.view', 'person.manage',
            'tenant.manage',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'admin')->first();
        $admin?->givePermissionTo($permissions);

        $manager = Role::where('name', 'manager')->first();
        $manager?->givePermissionTo(['building.view', 'flat.view', 'person.view']);
    }
}
