<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class GovernancePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminOnly = ['due.manage', 'asset.manage', 'meeting.manage'];
        $managerAlso = ['due.view', 'asset.view', 'meeting.view', 'complaint.manage', 'complaint.view'];

        foreach (array_merge($adminOnly, $managerAlso) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::where('name', 'admin')->first()?->givePermissionTo(array_merge($adminOnly, $managerAlso));
        // Complaint status updates (PRD: "অভিযোগ আপডেট") are explicitly a
        // Manager duty; dues/assets/meetings are committee-level business.
        Role::where('name', 'manager')->first()?->givePermissionTo(['complaint.manage', 'complaint.view']);
    }
}
