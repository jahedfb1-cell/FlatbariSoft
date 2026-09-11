<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\ExpenseHead;
use Illuminate\Database\Seeder;

class ExpenseHeadSeeder extends Seeder
{
    public function run(): void
    {
        $heads = [
            ['code' => 'STAFF_SALARY', 'name_bn' => 'স্টাফ বেতন', 'name_en' => 'Staff Salary', 'account_code' => '5100'],
            ['code' => 'UTILITY', 'name_bn' => 'ইউটিলিটি খরচ', 'name_en' => 'Utility', 'account_code' => '5200'],
            ['code' => 'CITY_CORPORATION', 'name_bn' => 'সিটি কর্পোরেশন খরচ', 'name_en' => 'City Corporation', 'account_code' => '5300'],
            ['code' => 'MAINTENANCE', 'name_bn' => 'রক্ষণাবেক্ষণ', 'name_en' => 'Maintenance', 'account_code' => '5400'],
            ['code' => 'GAS_PURCHASE', 'name_bn' => 'গ্যাস ক্রয়', 'name_en' => 'Gas Purchase', 'account_code' => '5500'],
            ['code' => 'OTHER', 'name_bn' => 'অন্যান্য', 'name_en' => 'Other', 'account_code' => '5900'],
        ];

        foreach ($heads as $head) {
            $accountId = ChartOfAccount::where('code', $head['account_code'])->value('id');
            ExpenseHead::firstOrCreate(['code' => $head['code']], [
                'name_bn' => $head['name_bn'],
                'name_en' => $head['name_en'],
                'account_id' => $accountId,
            ]);
        }
    }
}
