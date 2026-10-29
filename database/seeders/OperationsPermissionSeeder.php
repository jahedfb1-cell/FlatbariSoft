<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for the running-the-building tools: office settings, the audit log, backups, the gate/visitor
 * book and the yearly budget. As with the other seeders, the starting grants are made only when a permission
 * is first created, so a re-run never undoes what was changed on the Roles screen.
 *
 *  setting.manage   change late fee, due day, reminders, SMS on/off
 *  audit.view       read the audit log (who changed what)
 *  backup.manage    download a full copy of the data
 *  visitor.view     see the visitor book
 *  visitor.manage   write visitors in and out (the gatekeeper)
 *  budget.view      see the budget against actual spending
 *  budget.manage    set the yearly budget
 */
class OperationsPermissionSeeder extends Seeder
{
    private const GRANTS = [
        'setting.manage' => ['admin', 'committee_president'],
        'audit.view' => ['admin', 'committee_president', 'committee_treasurer'],
        'backup.manage' => ['admin'],
        'visitor.view' => ['admin', 'manager', 'committee_president', 'committee_secretary', 'committee_member'],
        'visitor.manage' => ['admin', 'manager'],
        'budget.view' => ['admin', 'manager', 'committee_president', 'committee_secretary', 'committee_treasurer', 'committee_member'],
        'budget.manage' => ['admin', 'committee_president', 'committee_treasurer'],
    ];

    public function run(): void
    {
        foreach (self::GRANTS as $name => $roles) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if ($permission->wasRecentlyCreated) {
                foreach ($roles as $role) {
                    Role::where('name', $role)->first()?->givePermissionTo($permission);
                }
            }
        }
    }
}
