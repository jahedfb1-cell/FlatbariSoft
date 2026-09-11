<?php

namespace Database\Seeders;

use App\Models\CollectionHead;
use Illuminate\Database\Seeder;

/**
 * PRD BR-11: service charge is always billed to the OWNER; the gas bill is
 * billed to whoever actually occupies the flat (OCCUPANT — resolved at
 * billing time via flat_parties, not fixed to one role).
 */
class CollectionHeadSeeder extends Seeder
{
    public function run(): void
    {
        $heads = [
            ['code' => 'SERVICE_CHARGE', 'name_bn' => 'সার্ভিস চার্জ', 'name_en' => 'Service Charge', 'payer_type' => 'OWNER', 'charge_type' => 'RECURRING', 'sort_order' => 1],
            ['code' => 'GAS_BILL', 'name_bn' => 'গ্যাস বিল', 'name_en' => 'Gas Bill', 'payer_type' => 'OCCUPANT', 'charge_type' => 'RECURRING', 'sort_order' => 2],
            ['code' => 'DEV_FUND', 'name_bn' => 'উন্নয়ন তহবিল', 'name_en' => 'Development Fund', 'payer_type' => 'OWNER', 'charge_type' => 'SPECIAL_LEVY', 'sort_order' => 3],
            ['code' => 'LATE_FEE', 'name_bn' => 'লেট ফি', 'name_en' => 'Late Fee', 'payer_type' => 'OWNER', 'charge_type' => 'MISC', 'sort_order' => 4],
            ['code' => 'ASSOCIATION_FEE', 'name_bn' => 'সমিতির চাঁদা', 'name_en' => 'Association Fee', 'payer_type' => 'OWNER', 'charge_type' => 'MISC', 'sort_order' => 5],
            ['code' => 'OTHER', 'name_bn' => 'অন্যান্য', 'name_en' => 'Other', 'payer_type' => 'OWNER', 'charge_type' => 'MISC', 'sort_order' => 6],
        ];

        foreach ($heads as $head) {
            CollectionHead::firstOrCreate(['code' => $head['code']], $head);
        }
    }
}
