<?php

namespace Database\Seeders;

use App\Models\ContributionHead;
use App\Models\ContributionSetting;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Construction-cost collection setup. The heads are the kinds of money that make up the building's
 * construction cost; the default target is the committee's figure per flat (editable in the app).
 * Role grants are made only when a permission is first created, so a re-run never undoes edits.
 *
 *  contribution.view     see the flat-wise statement and any flat's ledger
 *  contribution.collect  record a receipt
 *  contribution.manage   targets, loading earlier collections, reversing a wrong receipt
 */
class ContributionSeeder extends Seeder
{
    private const GRANTS = [
        'contribution.view' => ['admin', 'manager', 'committee_president', 'committee_secretary', 'committee_treasurer', 'committee_member'],
        'contribution.collect' => ['admin', 'manager', 'committee_treasurer'],
        'contribution.manage' => ['admin', 'committee_president'],
    ];

    public const HEADS = [
        ['SUBSCRIPTION', 'চাঁদা (Subscription)', 'Subscription'],
        ['GAS_LINE', 'গ্যাস লাইন খরচ', 'Gas line'],
        ['GENERATOR', 'জেনারেটর ক্রয়', 'Generator'],
        ['WATER_DUES', 'পানির পুরনো বকেয়া (WASA)', 'Water old dues'],
        ['EAST_WALL', 'পূর্ব পাশের দেয়াল', 'East wall'],
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

        foreach (self::HEADS as $i => [$code, $bn, $en]) {
            ContributionHead::firstOrCreate(['code' => $code], ['name_bn' => $bn, 'name_en' => $en, 'counts_toward_target' => true, 'sort_order' => $i + 1]);
        }

        if (! ContributionSetting::query()->exists()) {
            ContributionSetting::create(['default_target' => 2550000]); // 25,50,000 per flat, as decided by the committee
        }
    }
}
