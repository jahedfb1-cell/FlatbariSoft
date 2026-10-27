<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Parking setup: permissions, the income account, and who gets what the first time. The role grants are
 * made only when a permission is first created, so a re-run never undoes changes made on the Roles screen.
 *
 *  parking.view      see slots, holders and charges
 *  parking.manage    add/edit slots, allocate and release, generate the monthly charges
 *  parking.payments  collect parking money
 */
class ParkingSeeder extends Seeder
{
    private const GRANTS = [
        'parking.view' => ['admin', 'manager', 'committee_president', 'committee_secretary', 'committee_treasurer', 'committee_member'],
        'parking.manage' => ['admin', 'committee_president'],
        'parking.payments' => ['admin', 'manager', 'committee_treasurer'],
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

        $parent = ChartOfAccount::where('code', '4000')->first();
        if ($parent) {
            ChartOfAccount::firstOrCreate(['code' => '4700'], [
                'name_bn' => 'পার্কিং আয়', 'name_en' => 'Parking Income', 'account_type' => 'INCOME', 'parent_id' => $parent->id,
            ]);
        }
    }
}
