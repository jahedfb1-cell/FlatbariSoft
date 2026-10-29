<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Users & Roles list shows which flat each resident login holds. */
class UserListFlatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_user_row_lists_the_flats_that_person_holds_today(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->assignRole('super_admin');

        $person = Person::factory()->create();
        $resident = User::factory()->create(['person_id' => $person->id, 'role' => 'flat_owner']);
        $current = Flat::factory()->create(['flat_no' => '10A']);
        $old = Flat::factory()->create(['flat_no' => '3B']);
        FlatParty::create(['flat_id' => $current->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2026-01-01']);
        FlatParty::create(['flat_id' => $old->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2020-01-01', 'to_date' => '2024-01-01']);

        $rows = collect($this->actingAs($admin, 'sanctum')->getJson('/api/users')->assertOk()->json('data'));

        $this->assertSame([['flat_no' => '10A', 'role' => 'OWNER']], $rows->firstWhere('id', $resident->id)['flats']);
        $this->assertSame([], $rows->firstWhere('id', $admin->id)['flats']);   // office staff hold no flat
    }
}
