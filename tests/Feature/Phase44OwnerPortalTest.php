<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase44OwnerPortalTest extends TestCase
{
    use RefreshDatabase;

    private function ownerUser(): array
    {
        $person = Person::factory()->create();
        $user = User::factory()->create(['person_id' => $person->id, 'role' => 'flat_owner']);
        $flat = Flat::factory()->create();
        FlatParty::create(['flat_id' => $flat->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2026-01-01']);

        return [$user, $flat, $person];
    }

    public function test_owner_sees_only_their_own_flat(): void
    {
        [$user, $flat] = $this->ownerUser();
        $otherFlat = Flat::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me/flats');

        $response->assertStatus(200);
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($flat->id, $ids);
        $this->assertNotContains($otherFlat->id, $ids);
    }

    public function test_owner_sees_only_bills_for_their_own_flat(): void
    {
        [$user, $flat] = $this->ownerUser();
        $otherFlat = Flat::factory()->create();
        $period = BillingPeriod::create(['year' => 2026, 'month' => 8, 'status' => 'GENERATED']);

        $myBill = Bill::create(['billing_period_id' => $period->id, 'flat_id' => $flat->id, 'service_charge' => 3500, 'total_payable' => 3500, 'due_date' => '2026-08-10', 'status' => 'UNPAID']);
        Bill::create(['billing_period_id' => $period->id, 'flat_id' => $otherFlat->id, 'service_charge' => 3500, 'total_payable' => 3500, 'due_date' => '2026-08-10', 'status' => 'UNPAID']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me/bills');

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($myBill->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_owner_cannot_submit_a_complaint_for_a_flat_that_is_not_theirs(): void
    {
        [$user] = $this->ownerUser();
        $otherFlat = Flat::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/me/complaints', [
            'flat_id' => $otherFlat->id, 'category' => 'Plumbing', 'description' => 'Leak',
        ]);

        $response->assertStatus(422);
    }

    public function test_owner_can_submit_a_complaint_for_their_own_flat(): void
    {
        [$user, $flat] = $this->ownerUser();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/me/complaints', [
            'flat_id' => $flat->id, 'category' => 'Plumbing', 'description' => 'Leak',
        ]);

        $response->assertStatus(201)->assertJson(['flat_id' => $flat->id, 'status' => 'OPEN']);
    }
}
