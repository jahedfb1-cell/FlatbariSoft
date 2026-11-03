<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StaffPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A staff member's full KYC form and the papers (CV, NID, photo) kept with it. */
class StaffKycTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        foreach ([ChartOfAccountsSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, StaffPermissionSeeder::class] as $s) {
            $this->seed($s);
        }
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->staff = Staff::factory()->create();
    }

    private function body(array $over = []): array
    {
        return $over + [
            'nid' => '1234567890123', 'father_name' => 'Abdul', 'mother_name' => 'Amena', 'date_of_birth' => '1990-05-10',
            'gender' => 'MALE', 'marital_status' => 'MARRIED', 'blood_group' => 'B+', 'present_address' => 'Dhaka', 'permanent_address' => 'Cumilla',
            'emergency_name' => 'Karim', 'emergency_phone' => '01711111111', 'education' => 'HSC',
            'family_members' => [['name' => 'Salma', 'relation' => 'SPOUSE', 'age' => 28], ['name' => 'Rafi', 'relation' => 'SON', 'age' => 4]],
        ];
    }

    public function test_a_new_file_lists_everything_that_is_missing(): void
    {
        $res = $this->actingAs($this->admin, 'sanctum')->getJson("/api/masters/staff/{$this->staff->id}/kyc")->assertOk();

        $this->assertFalse($res->json('complete'));
        $this->assertContains('doc:CV', $res->json('missing'));
        $this->assertContains('present_address', $res->json('missing'));
    }

    public function test_the_form_saves_and_comes_back_with_the_family(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->putJson("/api/masters/staff/{$this->staff->id}/kyc", $this->body())->assertOk()
            ->assertJsonPath('father_name', 'Abdul')->assertJsonPath('date_of_birth', '1990-05-10')->assertJsonCount(2, 'family_members');

        $this->assertNotNull($this->staff->fresh()->kyc_updated_at);
    }

    public function test_bad_values_are_refused(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $url = "/api/masters/staff/{$this->staff->id}/kyc";
        $api->putJson($url, $this->body(['nid' => '12']))->assertStatus(422)->assertJsonValidationErrors('nid');
        $api->putJson($url, $this->body(['emergency_phone' => 'abc']))->assertStatus(422)->assertJsonValidationErrors('emergency_phone');
        $api->putJson($url, $this->body(['date_of_birth' => '2999-01-01']))->assertStatus(422)->assertJsonValidationErrors('date_of_birth');
        $api->putJson($url, $this->body(['family_members' => [['name' => 'X', 'relation' => 'ALIEN']]]))->assertStatus(422);
    }

    public function test_cv_nid_and_photo_complete_the_file_and_are_served_privately(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->putJson("/api/masters/staff/{$this->staff->id}/kyc", $this->body())->assertOk();

        $up = fn (string $type, UploadedFile $f) => $api->post("/api/masters/staff/{$this->staff->id}/documents", ['type' => $type, 'files' => [$f]], ['Accept' => 'application/json']);
        $up('CV', UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf'))->assertCreated();
        $up('NID', UploadedFile::fake()->image('nid.jpg'))->assertCreated();
        $res = $up('PHOTO', UploadedFile::fake()->image('me.png'))->assertCreated();

        $this->assertTrue($res->json('complete'));
        $this->assertCount(3, $res->json('documents'));
        $this->assertArrayNotHasKey('path', $res->json('documents.0'));

        $id = $res->json('documents.0.id');
        $api->get("/api/masters/staff/documents/{$id}")->assertOk();

        $api->deleteJson("/api/masters/staff/documents/{$id}")->assertOk();
        $this->assertFalse($api->getJson("/api/masters/staff/{$this->staff->id}/kyc")->json('complete'));
    }

    public function test_only_pdf_and_images_are_accepted(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->post("/api/masters/staff/{$this->staff->id}/documents", ['type' => 'CV', 'files' => [UploadedFile::fake()->create('virus.exe', 10)]], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_people_without_staff_permission_cannot_see_the_papers(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $tenant->assignRole('tenant');

        $this->actingAs($tenant, 'sanctum')->getJson("/api/masters/staff/{$this->staff->id}/kyc")->assertForbidden();
    }
}
