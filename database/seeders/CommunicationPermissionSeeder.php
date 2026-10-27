<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CommunicationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // PRD: "নোটিশ পাঠানো" is explicitly an Admin duty, not Manager's.
        foreach (['notice.manage', 'notice.view'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::where('name', 'admin')->first()?->givePermissionTo(['notice.manage', 'notice.view']);
    }
}
