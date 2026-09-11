<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            FlatMasterPermissionSeeder::class,
            DocumentPermissionSeeder::class,
            StaffPermissionSeeder::class,
            BuildingSeeder::class,
            CollectionHeadSeeder::class,
            ChartOfAccountsSeeder::class,
            MoneyCorePermissionSeeder::class,
            GasSetupSeeder::class,
            ExpenseHeadSeeder::class,
            GovernancePermissionSeeder::class,
        ]);
    }
}
