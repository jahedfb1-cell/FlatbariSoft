<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['code' => '1000', 'name_bn' => 'সম্পদ', 'name_en' => 'Assets', 'account_type' => 'ASSET'],
            ['code' => '2000', 'name_bn' => 'দায়', 'name_en' => 'Liabilities', 'account_type' => 'LIABILITY'],
            ['code' => '3000', 'name_bn' => 'তহবিল', 'name_en' => 'Funds', 'account_type' => 'FUND'],
            ['code' => '4000', 'name_bn' => 'আয়', 'name_en' => 'Income', 'account_type' => 'INCOME'],
            ['code' => '5000', 'name_bn' => 'ব্যয়', 'name_en' => 'Expenses', 'account_type' => 'EXPENSE'],
        ];

        $groupIds = [];
        foreach ($groups as $group) {
            $groupIds[$group['code']] = ChartOfAccount::firstOrCreate(['code' => $group['code']], $group)->id;
        }

        $children = [
            // Assets
            ['code' => '1100', 'name_bn' => 'ব্যাংক হিসাব', 'name_en' => 'Bank Accounts', 'account_type' => 'ASSET', 'parent' => '1000'],
            ['code' => '1200', 'name_bn' => 'হাতে নগদ', 'name_en' => 'Cash in Hand', 'account_type' => 'ASSET', 'parent' => '1000'],
            ['code' => '1300', 'name_bn' => 'প্রাপ্য বকেয়া', 'name_en' => 'Accounts Receivable', 'account_type' => 'ASSET', 'parent' => '1000'],

            // Liabilities
            ['code' => '2100', 'name_bn' => 'সিকিউরিটি ডিপোজিট (ভাড়াটিয়া)', 'name_en' => 'Security Deposits Payable', 'account_type' => 'LIABILITY', 'parent' => '2000'],
            ['code' => '2200', 'name_bn' => 'প্রদেয় হিসাব', 'name_en' => 'Accounts Payable', 'account_type' => 'LIABILITY', 'parent' => '2000'],

            // Funds
            ['code' => '3100', 'name_bn' => 'পরিচালন তহবিল', 'name_en' => 'Operating Fund', 'account_type' => 'FUND', 'parent' => '3000'],
            ['code' => '3200', 'name_bn' => 'মূলধন তহবিল', 'name_en' => 'Capital Fund', 'account_type' => 'FUND', 'parent' => '3000'],
            ['code' => '3300', 'name_bn' => 'সঞ্চিত তহবিল', 'name_en' => 'Reserve Fund', 'account_type' => 'FUND', 'parent' => '3000'],

            // Income
            ['code' => '4100', 'name_bn' => 'সার্ভিস চার্জ আয়', 'name_en' => 'Service Charge Income', 'account_type' => 'INCOME', 'parent' => '4000'],
            ['code' => '4200', 'name_bn' => 'গ্যাস বিল আয়', 'name_en' => 'Gas Bill Income', 'account_type' => 'INCOME', 'parent' => '4000'],
            ['code' => '4300', 'name_bn' => 'লেট ফি আয়', 'name_en' => 'Late Fee Income', 'account_type' => 'INCOME', 'parent' => '4000'],
            ['code' => '4400', 'name_bn' => 'সমিতির চাঁদা আয়', 'name_en' => 'Association Fee Income', 'account_type' => 'INCOME', 'parent' => '4000'],
            ['code' => '4500', 'name_bn' => 'উন্নয়ন তহবিল আয়', 'name_en' => 'Development Fund Income', 'account_type' => 'INCOME', 'parent' => '4000'],
            ['code' => '4900', 'name_bn' => 'অন্যান্য আয়', 'name_en' => 'Other Income', 'account_type' => 'INCOME', 'parent' => '4000'],

            // Expenses
            ['code' => '5100', 'name_bn' => 'স্টাফ বেতন', 'name_en' => 'Staff Salary', 'account_type' => 'EXPENSE', 'parent' => '5000'],
            ['code' => '5200', 'name_bn' => 'ইউটিলিটি খরচ', 'name_en' => 'Utility Expense', 'account_type' => 'EXPENSE', 'parent' => '5000'],
            ['code' => '5300', 'name_bn' => 'সিটি কর্পোরেশন খরচ', 'name_en' => 'City Corporation Expense', 'account_type' => 'EXPENSE', 'parent' => '5000'],
            ['code' => '5400', 'name_bn' => 'রক্ষণাবেক্ষণ খরচ', 'name_en' => 'Maintenance Expense', 'account_type' => 'EXPENSE', 'parent' => '5000'],
            ['code' => '5500', 'name_bn' => 'গ্যাস ক্রয় খরচ', 'name_en' => 'Gas Purchase Expense', 'account_type' => 'EXPENSE', 'parent' => '5000'],
            ['code' => '5600', 'name_bn' => 'নির্মাণ সামগ্রী খরচ', 'name_en' => 'Construction Materials Expense', 'account_type' => 'EXPENSE', 'parent' => '5000'],
            ['code' => '5900', 'name_bn' => 'অন্যান্য খরচ', 'name_en' => 'Other Expense', 'account_type' => 'EXPENSE', 'parent' => '5000'],
        ];

        foreach ($children as $child) {
            $parentCode = $child['parent'];
            unset($child['parent']);
            $child['parent_id'] = $groupIds[$parentCode];
            ChartOfAccount::firstOrCreate(['code' => $child['code']], $child);
        }
    }
}
