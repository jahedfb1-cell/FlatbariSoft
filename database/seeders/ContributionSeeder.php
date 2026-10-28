<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\Contribution;
use App\Models\ContributionBook;
use App\Models\ContributionHead;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Collection books setup. The books themselves (construction cost, flat, parking sale) are created by the
 * migration; this adds their heads, the fund accounts receipts are credited to, and the permissions.
 * Role grants are made only when a permission is first created, so a re-run never undoes edits.
 *
 *  contribution.view     see statements and any flat's ledger
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

    /** The construction cost is made of these; the other books' heads come with the migration. */
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

        $construction = ContributionBook::where('code', 'CONSTRUCTION')->firstOrFail();
        foreach (self::HEADS as $i => [$code, $bn, $en]) {
            ContributionHead::firstOrCreate(['code' => $code], [
                'contribution_book_id' => $construction->id, 'name_bn' => $bn, 'name_en' => $en,
                'counts_toward_target' => true, 'sort_order' => $i + 1,
            ]);
        }
        // The committee's figure: 25,50,000 per flat. Only filled in while untouched, so an edit is never undone.
        if (bccomp((string) $construction->default_target, '0', 4) === 0 && ! Contribution::query()->exists()) {
            $construction->update(['default_target' => 2550000]);
        }

        // Flat and parking money is a fund of its own, not income: the accountant decides how to treat it later.
        $parent = ChartOfAccount::where('code', '3000')->first();
        if ($parent) {
            foreach ([['3400', 'ফ্ল্যাট আদায় তহবিল', 'Flat Collections Fund'], ['3500', 'পার্কিং বিক্রয় আদায় তহবিল', 'Parking Sale Collections Fund']] as [$code, $bn, $en]) {
                ChartOfAccount::firstOrCreate(['code' => $code], ['name_bn' => $bn, 'name_en' => $en, 'account_type' => 'FUND', 'parent_id' => $parent->id]);
            }
        }
    }
}
