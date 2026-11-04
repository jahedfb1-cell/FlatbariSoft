<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bank account holders are picked from the building's flat owners; their name and phone come from the owner's own record. */
class BankHoldersAreOwnersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class] as $s) {
            $this->seed($s);
        }
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
    }

    private function owner(string $flatNo, string $name, string $mobile): Person
    {
        $p = Person::factory()->create(['name_bn' => $name, 'name_en' => null, 'mobile' => $mobile]);
        FlatParty::create(['flat_id' => Flat::factory()->create(['flat_no' => $flatNo])->id, 'person_id' => $p->id, 'role' => 'OWNER', 'from_date' => '2026-01-01']);

        return $p;
    }

    private function body(array $over = []): array
    {
        return $over + ['bank_name' => 'DBBL', 'account_no' => '123', 'account_title' => 'Green Valley'];
    }

    public function test_the_owner_list_has_one_entry_per_person_with_their_flats(): void
    {
        $a = $this->owner('2A', 'Karim', '01711111111');
        FlatParty::create(['flat_id' => Flat::factory()->create(['flat_no' => '4D'])->id, 'person_id' => $a->id, 'role' => 'OWNER', 'from_date' => '2026-01-01']);
        $this->owner('3B', 'Rahim', '01722222222');
        // a tenant is not an owner
        FlatParty::create(['flat_id' => Flat::first()->id, 'person_id' => Person::factory()->create()->id, 'role' => 'TENANT', 'from_date' => '2026-01-01']);

        $list = $this->actingAs($this->admin, 'sanctum')->getJson('/api/bank-accounts/owners')->assertOk()->json();

        $this->assertCount(2, $list);
        $karim = collect($list)->firstWhere('name', 'Karim');
        $this->assertSame('2A, 4D', $karim['flats']);
    }

    public function test_a_holder_picked_from_the_owners_takes_the_name_and_phone_from_the_person(): void
    {
        $karim = $this->owner('2A', 'Karim', '01711111111');
        $api = $this->actingAs($this->admin, 'sanctum');

        $res = $api->postJson('/api/bank-accounts', $this->body(['holder_1_person_id' => $karim->id, 'holder_1_name' => 'someone typed this', 'holder_1_phone' => '01999999999']))->assertSuccessful();

        $h = $res->json('holders.0');
        $this->assertSame('Karim', $h['name']); // the server fills it in; whatever was typed beside it is ignored
        $this->assertSame('01711111111', $h['phone']);
        $this->assertSame($karim->id, $h['person_id']);
    }

    public function test_someone_who_is_not_a_flat_owner_cannot_be_picked(): void
    {
        $outsider = Person::factory()->create();

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/bank-accounts', $this->body(['holder_2_person_id' => $outsider->id]))
            ->assertStatus(422)->assertJsonValidationErrors('holder_2_person_id');
    }

    public function test_a_holder_can_be_changed_to_another_owner_or_typed_by_hand(): void
    {
        $karim = $this->owner('2A', 'Karim', '01711111111');
        $rahim = $this->owner('3B', 'Rahim', '01722222222');
        $api = $this->actingAs($this->admin, 'sanctum');
        $id = $api->postJson('/api/bank-accounts', $this->body(['holder_1_person_id' => $karim->id]))->json('id');

        $api->putJson("/api/bank-accounts/{$id}", ['holder_1_person_id' => $rahim->id])->assertOk()->assertJsonPath('holders.0.name', 'Rahim');
        $api->putJson("/api/bank-accounts/{$id}", ['holder_1_person_id' => null, 'holder_1_name' => 'Outside Signatory', 'holder_1_phone' => '01733333333'])
            ->assertOk()->assertJsonPath('holders.0.name', 'Outside Signatory')->assertJsonPath('holders.0.person_id', null);
    }
}
