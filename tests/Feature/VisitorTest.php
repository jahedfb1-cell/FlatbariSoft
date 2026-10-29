<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\Person;
use App\Models\User;
use App\Models\Visitor;
use Database\Seeders\OperationsPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(\Database\Seeders\RoleCatalogSeeder::class);
        $this->seed(OperationsPermissionSeeder::class);
    }

    private function user(string $role): User
    {
        $u = User::factory()->create(['role' => $role]);
        $u->assignRole($role);

        return $u;
    }

    public function test_gate_writes_a_visitor_in_and_out(): void
    {
        $guard = $this->user('manager');
        $flat = Flat::factory()->create();
        $api = $this->actingAs($guard, 'sanctum');

        $in = $api->postJson('/api/visitors', ['flat_id' => $flat->id, 'visitor_name' => 'Rahim', 'purpose' => 'DELIVERY', 'vehicle_no' => 'DHA-1'])
            ->assertCreated()->assertJsonPath('inside', true);
        $api->getJson('/api/visitors?inside=1')->assertJsonPath('inside_now', 1);

        $api->postJson('/api/visitors/'.$in->json('id').'/checkout')->assertOk()->assertJsonPath('inside', false);
        $api->getJson('/api/visitors?inside=1')->assertJsonPath('inside_now', 0)->assertJsonCount(0, 'visitors');
        $this->assertCount(1, $api->getJson('/api/visitors?q=Rahim')->json('visitors'));
    }

    public function test_validation_and_permissions(): void
    {
        $api = $this->actingAs($this->user('manager'), 'sanctum');
        $api->postJson('/api/visitors', ['visitor_name' => 'x', 'purpose' => 'GUEST'])->assertStatus(422);
        $api->postJson('/api/visitors', ['flat_id' => Flat::factory()->create()->id, 'visitor_name' => 'x', 'purpose' => 'SPY'])->assertStatus(422);

        $member = $this->user('committee_member');   // may read, not write
        $this->actingAs($member, 'sanctum')->getJson('/api/visitors')->assertOk();
        $this->actingAs($member, 'sanctum')->postJson('/api/visitors', ['flat_id' => Flat::factory()->create()->id, 'visitor_name' => 'x', 'purpose' => 'GUEST'])->assertForbidden();
    }

    public function test_a_resident_sees_only_their_own_flats_visitors(): void
    {
        $person = Person::factory()->create();
        $resident = User::factory()->create(['person_id' => $person->id, 'role' => 'flat_owner']);
        $mine = Flat::factory()->create();
        $other = Flat::factory()->create();
        FlatParty::create(['flat_id' => $mine->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2026-01-01']);
        Visitor::create(['flat_id' => $mine->id, 'visitor_name' => 'For me', 'purpose' => 'GUEST', 'in_at' => now()]);
        Visitor::create(['flat_id' => $other->id, 'visitor_name' => 'Not me', 'purpose' => 'GUEST', 'in_at' => now()]);

        $names = collect($this->actingAs($resident, 'sanctum')->getJson('/api/me/visitors')->json())->pluck('visitor_name')->all();
        $this->assertSame(['For me'], $names);
        $this->actingAs($resident, 'sanctum')->getJson('/api/visitors')->assertForbidden();
    }
}
