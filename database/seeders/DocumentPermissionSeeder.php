<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for Phase 0.6 (owner/tenant documents). Same reasoning as
 * FlatMasterPermissionSeeder: admin manages, manager only views, since
 * document upload isn't among Manager's PRD-listed duties either.
 */
class DocumentPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = ['document.view', 'document.manage'];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::where('name', 'admin')->first()?->givePermissionTo($permissions);
        Role::where('name', 'manager')->first()?->givePermissionTo('document.view');
    }
}
