<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\CommunityHall;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Community-hall booking setup. Idempotent, so it is also safe on a database that already has data.
 *
 * Rates are seeded as NULL (not decided) on purpose: a booking is refused until the office sets real
 * rates, so a placeholder price can never end up on a customer's bill. A rate of 0 later means "free".
 *
 *  hall.view     read the calendar and bookings          (admin, manager, committee)
 *  hall.request  take a booking; it stays PENDING        (admin, manager, secretary)
 *  hall.manage   change hall settings and rates          (admin, president; super_admin bypasses)
 *
 * hall.approve and hall.payments are created by RoleCatalogSeeder together with the committee roles.
 */
class CommunityHallSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['hall.view', 'hall.request', 'hall.manage'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Role::where('name', 'admin')->first()?->givePermissionTo(['hall.view', 'hall.request', 'hall.manage']);
        Role::where('name', 'manager')->first()?->givePermissionTo(['hall.view', 'hall.request']);

        // Rent is income; the damage deposit is a refundable liability, never income.
        $this->account('4600', 'কমিউনিটি হল ভাড়া আয়', 'Community Hall Rent Income', 'INCOME', '4000');
        $this->account('2300', 'কমিউনিটি হল সিকিউরিটি ডিপোজিট', 'Community Hall Security Deposit', 'LIABILITY', '2000');

        $hall = CommunityHall::firstOrCreate(['name' => 'কমিউনিটি হল'], [
            'building_id' => \App\Models\Building::query()->value('id'),
            'capacity' => 0,
            'security_deposit' => 0,
            'advance_percent' => 50,
            'free_cancel_days' => 7,
            'late_cancel_refund_percent' => 50,
            'is_active' => true,
        ]);

        foreach ([
            ['DAY', '09:00', '16:00'],
            ['NIGHT', '18:00', '23:00'],
            ['FULL_DAY', '09:00', '23:00'],
        ] as [$slot, $start, $end]) {
            $hall->rates()->firstOrCreate(['slot' => $slot], [
                'start_time' => $start, 'end_time' => $end, 'resident_rate' => null, 'outsider_rate' => null,
            ]);
        }
    }

    private function account(string $code, string $bn, string $en, string $type, string $parentCode): void
    {
        $parent = ChartOfAccount::where('code', $parentCode)->first();
        if (! $parent) {
            return; // the base chart of accounts isn't seeded yet; ChartOfAccountsSeeder must run first
        }

        ChartOfAccount::firstOrCreate(['code' => $code], [
            'name_bn' => $bn, 'name_en' => $en, 'account_type' => $type, 'parent_id' => $parent->id,
        ]);
    }
}
