<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\FlatMasterPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The tenant KYC form: NID, phones, work, emergency contact, and the family who will live in the flat. */
class TenantKycTest extends TestCase
{
    use RefreshDatabase;

    private User $tenantUser;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(FlatMasterPermissionSeeder::class);
        $person = Person::factory()->create(['nid' => null, 'address' => null]);
        $this->tenantUser = User::factory()->create(['person_id' => $person->id, 'role' => 'tenant']);
        $flat = Flat::factory()->create();
        $this->tenant = Tenant::create(['flat_id' => $flat->id, 'person_id' => $person->id, 'start_date' => '2026-09-01', 'status' => 'ACTIVE']);
        FlatParty::create(['flat_id' => $flat->id, 'person_id' => $person->id, 'role' => 'TENANT', 'from_date' => '2026-09-01']);
    }

    private function form(array $over = []): array
    {
        return array_replace_recursive([
            'person' => ['name_bn' => 'রহিম উদ্দিন', 'name_en' => 'Rahim Uddin', 'nid' => '1234567890', 'alt_mobile' => '01812345678', 'email' => 'r@example.com', 'address' => 'Village X, Cumilla'],
            'occupation' => 'Engineer', 'workplace' => 'ABC Ltd', 'emergency_name' => 'Karim', 'emergency_phone' => '01711111111',
            'previous_address' => 'Mirpur, Dhaka', 'reference_name' => 'Salam', 'reference_phone' => '01922222222', 'other_info' => 'Two cats',
            'family_members' => [
                ['name' => 'Salma', 'relation' => 'SPOUSE', 'age' => 30, 'mobile' => '01733333333'],
                ['name' => 'Rafi', 'relation' => 'SON', 'age' => 5],
            ],
        ], $over);
    }

    public function test_a_new_tenant_starts_with_an_incomplete_form(): void
    {
        $res = $this->actingAs($this->tenantUser, 'sanctum')->getJson('/api/me/kyc')->assertOk();

        $this->assertFalse($res->json('complete'));
        $this->assertEqualsCanonicalizing(['nid', 'address', 'occupation', 'emergency_name', 'emergency_phone'], $res->json('missing'));
    }

    public function test_the_tenant_fills_their_own_form_and_it_completes(): void
    {
        $res = $this->actingAs($this->tenantUser, 'sanctum')->putJson('/api/me/kyc', $this->form())->assertOk();

        $this->assertTrue($res->json('complete'));
        $this->assertNotNull($res->json('submitted_at'));
        $this->assertCount(2, $res->json('family_members'));
        $this->assertSame('1234567890', $res->json('person.nid'));
        $this->assertSame('Two cats', $res->json('other_info'));

        $this->assertDatabaseHas('persons', ['id' => $this->tenant->person_id, 'nid' => '1234567890', 'address' => 'Village X, Cumilla']);
        $this->assertSame(2, $this->tenant->fresh()->family_members_count);
    }

    public function test_saving_again_replaces_the_family_list(): void
    {
        $api = $this->actingAs($this->tenantUser, 'sanctum');
        $api->putJson('/api/me/kyc', $this->form())->assertOk();
        $res = $api->putJson('/api/me/kyc', $this->form(['family_members' => [['name' => 'Only One', 'relation' => 'FATHER']]]));
        // array_replace_recursive merges lists by index, so send an explicit single-member list
        $res = $api->putJson('/api/me/kyc', array_merge($this->form(), ['family_members' => [['name' => 'Only One', 'relation' => 'FATHER']]]))->assertOk();

        $this->assertCount(1, $res->json('family_members'));
        $this->assertSame('Only One', $res->json('family_members.0.name'));
        $this->assertDatabaseCount('tenant_family_members', 1);
    }

    public function test_the_nid_phones_and_family_rows_are_validated(): void
    {
        $api = $this->actingAs($this->tenantUser, 'sanctum');

        $api->putJson('/api/me/kyc', $this->form(['person' => ['nid' => '123']]))->assertStatus(422)->assertJsonValidationErrors('person.nid');
        $api->putJson('/api/me/kyc', $this->form(['emergency_phone' => '12345']))->assertStatus(422)->assertJsonValidationErrors('emergency_phone');
        $api->putJson('/api/me/kyc', array_merge($this->form(), ['family_members' => [['name' => '', 'relation' => 'SPOUSE']]]))->assertStatus(422)->assertJsonValidationErrors('family_members.0.name');
        $api->putJson('/api/me/kyc', array_merge($this->form(), ['family_members' => [['name' => 'X', 'relation' => 'ALIEN']]]))->assertStatus(422)->assertJsonValidationErrors('family_members.0.relation');
        $api->putJson('/api/me/kyc', array_merge($this->form(), ['family_members' => array_fill(0, 16, ['name' => 'X', 'relation' => 'OTHER'])]))->assertStatus(422)->assertJsonValidationErrors('family_members');
        $api->putJson('/api/me/kyc', $this->form(['occupation' => '']))->assertStatus(422)->assertJsonValidationErrors('occupation');
    }

    public function test_a_resident_who_is_not_a_tenant_has_no_form(): void
    {
        $person = Person::factory()->create();
        $owner = User::factory()->create(['person_id' => $person->id, 'role' => 'flat_owner']);

        $this->actingAs($owner, 'sanctum')->getJson('/api/me/kyc')->assertOk()->assertJson([]);
        $this->actingAs($owner, 'sanctum')->putJson('/api/me/kyc', $this->form())->assertNotFound();
    }

    public function test_a_vacated_tenancy_is_no_longer_the_tenants_form(): void
    {
        $this->tenant->update(['status' => 'VACATED']);
        $this->actingAs($this->tenantUser, 'sanctum')->putJson('/api/me/kyc', $this->form())->assertNotFound();
    }

    public function test_the_office_can_read_and_fill_any_tenants_form_but_residents_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $url = "/api/masters/tenants/{$this->tenant->id}/kyc";

        $this->actingAs($admin, 'sanctum')->putJson($url, $this->form())->assertOk()->assertJsonPath('complete', true);
        $this->actingAs($admin, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('person.nid', '1234567890');

        $this->actingAs($this->tenantUser, 'sanctum')->getJson($url)->assertForbidden();
        $this->actingAs($this->tenantUser, 'sanctum')->putJson($url, $this->form())->assertForbidden();
    }
}
