<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo Super Admin account — lets the OTP login (Phase 0.4) and every
 * later permission-gated route be tested end to end without a real
 * committee member's mobile number.
 *
 * Password login is temporary scaffolding: the PRD's real auth is
 * mobile + OTP (Phase 0.4). Until that lands, this password lets us
 * issue a Sanctum token via Tinker/curl for manual API testing.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['mobile' => '01700000001'],
            [
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'status' => 'ACTIVE',
            ],
        );

        $admin->assignRole('super_admin');
    }
}
