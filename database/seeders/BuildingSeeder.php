<?php

namespace Database\Seeders;

use App\Models\Building;
use Illuminate\Database\Seeder;

/**
 * The one real building this whole system is for (PRD section 1) — not
 * demo data. Flats, owners and tenants are NOT seeded here: that is real
 * business data the owner enters himself once this module is tested
 * end-to-end, same as the opening-stock pattern on the sibling WBL project.
 */
class BuildingSeeder extends Seeder
{
    public function run(): void
    {
        Building::firstOrCreate(
            ['name' => 'Green Valley'],
            [
                'address' => '৩৩৫/ঘ/১, টি.ভি রোড, পূর্ব রামপুরা, ঢাকা-১২১৯',
                // G+9 = ground floor + 9 upper floors = 10 levels.
                'total_floors' => 10,
                'total_flats' => 45,
            ],
        );
    }
}
