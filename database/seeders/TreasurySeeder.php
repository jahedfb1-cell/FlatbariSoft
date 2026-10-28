<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Setup for the monthly fund statements and the treasury entries behind them: the accounts those entries
 * need, and who may make them (treasury.manage). Reading the statements needs accounting.view.
 * Role grants happen only when the permission is first created, so a re-run never undoes edits.
 */
class TreasurySeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'treasury.manage', 'guard_name' => 'web']);
        if ($permission->wasRecentlyCreated) {
            foreach (['admin', 'committee_treasurer'] as $role) {
                Role::where('name', $role)->first()?->givePermissionTo($permission);
            }
        }

        $accounts = [
            ['3900', 'শুরুর ব্যালেন্স তহবিল', 'Opening Balance Equity', 'FUND', '3000'],
            ['1400', 'আন্তঃতহবিল প্রাপ্য (ধার দেওয়া)', 'Inter-fund Loans Receivable', 'ASSET', '1000'],
            ['2400', 'আন্তঃতহবিল দেনা (ধার নেওয়া)', 'Inter-fund Loans Payable', 'LIABILITY', '2000'],
        ];
        foreach ($accounts as [$code, $bn, $en, $type, $parentCode]) {
            $parent = ChartOfAccount::where('code', $parentCode)->first();
            if ($parent) {
                ChartOfAccount::firstOrCreate(['code' => $code], ['name_bn' => $bn, 'name_en' => $en, 'account_type' => $type, 'parent_id' => $parent->id]);
            }
        }
    }
}
