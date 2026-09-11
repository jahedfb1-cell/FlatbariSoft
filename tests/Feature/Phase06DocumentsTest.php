<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase06DocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\FlatMasterPermissionSeeder::class);
        $this->seed(\Database\Seeders\DocumentPermissionSeeder::class);

        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('admin');

        return $user;
    }

    // ---- Owner documents -----------------------------------------------------

    public function test_admin_can_upload_an_owner_document(): void
    {
        Storage::fake('public');
        $person = Person::factory()->create();
        $flat = Flat::factory()->create();
        $file = UploadedFile::fake()->create('nid.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'NID',
            'side' => 'FRONT',
            'file' => $file,
        ]);

        $response->assertStatus(201)->assertJson(['document_type' => 'NID', 'side' => 'FRONT']);
        $this->assertDatabaseHas('owner_documents', ['person_id' => $person->id, 'flat_id' => $flat->id]);
        Storage::disk('public')->assertExists("owner-documents/{$person->id}/".$file->hashName());
    }

    public function test_land_tax_receipt_requires_document_year(): void
    {
        Storage::fake('public');
        $person = Person::factory()->create();
        $flat = Flat::factory()->create();

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'LAND_TAX_RECEIPT',
            'file' => UploadedFile::fake()->create('receipt.pdf', 200, 'application/pdf'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('document_year');
    }

    public function test_land_tax_receipt_cannot_duplicate_the_same_year(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $person = Person::factory()->create();
        $flat = Flat::factory()->create();

        $this->actingAs($admin, 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'LAND_TAX_RECEIPT',
            'document_year' => 2026,
            'file' => UploadedFile::fake()->create('receipt-2026.pdf', 200, 'application/pdf'),
        ])->assertStatus(201);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'LAND_TAX_RECEIPT',
            'document_year' => 2026,
            'file' => UploadedFile::fake()->create('receipt-2026-again.pdf', 200, 'application/pdf'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('document_year');
    }

    public function test_land_tax_receipt_for_a_different_year_is_a_separate_entry_not_an_overwrite(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $person = Person::factory()->create();
        $flat = Flat::factory()->create();

        $this->actingAs($admin, 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'LAND_TAX_RECEIPT',
            'document_year' => 2025,
            'file' => UploadedFile::fake()->create('receipt-2025.pdf', 200, 'application/pdf'),
        ])->assertStatus(201);

        $this->actingAs($admin, 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'LAND_TAX_RECEIPT',
            'document_year' => 2026,
            'file' => UploadedFile::fake()->create('receipt-2026.pdf', 200, 'application/pdf'),
        ])->assertStatus(201);

        $this->assertSame(2, $person->fresh()->ownerDocuments()->where('document_type', 'LAND_TAX_RECEIPT')->count());
    }

    public function test_nid_requires_a_side(): void
    {
        Storage::fake('public');
        $person = Person::factory()->create();
        $flat = Flat::factory()->create();

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'NID',
            'file' => UploadedFile::fake()->create('nid.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('side');
    }

    public function test_nid_cannot_upload_the_same_side_twice(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $person = Person::factory()->create();
        $flat = Flat::factory()->create();

        $this->actingAs($admin, 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'NID',
            'side' => 'FRONT',
            'file' => UploadedFile::fake()->create('front.pdf', 100, 'application/pdf'),
        ])->assertStatus(201);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'NID',
            'side' => 'FRONT',
            'file' => UploadedFile::fake()->create('front-again.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('side');
    }

    public function test_nid_front_and_back_can_both_be_uploaded(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $person = Person::factory()->create();
        $flat = Flat::factory()->create();

        $this->actingAs($admin, 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'NID',
            'side' => 'FRONT',
            'file' => UploadedFile::fake()->create('front.pdf', 100, 'application/pdf'),
        ])->assertStatus(201);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'NID',
            'side' => 'BACK',
            'file' => UploadedFile::fake()->create('back.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(201)->assertJson(['side' => 'BACK']);
        $this->assertSame(2, $person->fresh()->ownerDocuments()->where('document_type', 'NID')->count());
    }

    public function test_non_nid_document_does_not_require_a_side(): void
    {
        Storage::fake('public');
        $person = Person::factory()->create();
        $flat = Flat::factory()->create();

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'NAMJARI',
            'file' => UploadedFile::fake()->create('namjari.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(201)->assertJson(['side' => null]);
    }

    public function test_disallowed_file_type_is_rejected(): void
    {
        Storage::fake('public');
        $person = Person::factory()->create();
        $flat = Flat::factory()->create();

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'NID',
            'side' => 'FRONT',
            'file' => UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_oversized_file_is_rejected(): void
    {
        Storage::fake('public');
        $person = Person::factory()->create();
        $flat = Flat::factory()->create();

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'NID',
            'side' => 'FRONT',
            'file' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf'), // 6MB > 5MB limit
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_manager_can_view_but_not_upload_owner_documents(): void
    {
        Storage::fake('public');
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\FlatMasterPermissionSeeder::class);
        $this->seed(\Database\Seeders\DocumentPermissionSeeder::class);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->assignRole('manager');
        $person = Person::factory()->create();

        $this->actingAs($manager, 'sanctum')->getJson("/api/masters/persons/{$person->id}/documents")->assertStatus(200);
        $this->actingAs($manager, 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => Flat::factory()->create()->id,
            'document_type' => 'NID',
            'side' => 'FRONT',
            'file' => UploadedFile::fake()->create('nid.pdf', 100, 'application/pdf'),
        ])->assertStatus(403);
    }

    public function test_deleting_an_owner_document_soft_deletes_but_keeps_the_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $person = Person::factory()->create();
        $flat = Flat::factory()->create();
        $file = UploadedFile::fake()->create('nid.pdf', 100, 'application/pdf');

        $doc = $this->actingAs($admin, 'sanctum')->postJson("/api/masters/persons/{$person->id}/documents", [
            'flat_id' => $flat->id,
            'document_type' => 'NID',
            'side' => 'FRONT',
            'file' => $file,
        ])->json();

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/masters/owner-documents/{$doc['id']}")->assertStatus(200);

        $this->assertSoftDeleted('owner_documents', ['id' => $doc['id']]);
        Storage::disk('public')->assertExists("owner-documents/{$person->id}/".$file->hashName());
    }

    // ---- Tenant documents -----------------------------------------------------

    public function test_admin_can_upload_a_tenant_document(): void
    {
        Storage::fake('public');
        $flat = Flat::factory()->create();
        $tenant = Tenant::factory()->for(Person::factory())->create(['flat_id' => $flat->id]);

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/tenants/{$tenant->id}/documents", [
            'document_type' => 'POLICE_VERIFICATION',
            'file' => UploadedFile::fake()->create('verification.pdf', 300, 'application/pdf'),
        ]);

        $response->assertStatus(201)->assertJson(['document_type' => 'POLICE_VERIFICATION']);
        $this->assertDatabaseHas('tenant_documents', ['tenant_id' => $tenant->id]);
    }

    public function test_tenant_nid_requires_a_side_and_rejects_duplicates(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $flat = Flat::factory()->create();
        $tenant = Tenant::factory()->for(Person::factory())->create(['flat_id' => $flat->id]);

        $this->actingAs($admin, 'sanctum')->postJson("/api/masters/tenants/{$tenant->id}/documents", [
            'document_type' => 'NID',
            'file' => UploadedFile::fake()->create('nid.pdf', 100, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('side');

        $this->actingAs($admin, 'sanctum')->postJson("/api/masters/tenants/{$tenant->id}/documents", [
            'document_type' => 'NID',
            'side' => 'FRONT',
            'file' => UploadedFile::fake()->create('front.pdf', 100, 'application/pdf'),
        ])->assertStatus(201);

        $this->actingAs($admin, 'sanctum')->postJson("/api/masters/tenants/{$tenant->id}/documents", [
            'document_type' => 'NID',
            'side' => 'FRONT',
            'file' => UploadedFile::fake()->create('front-again.pdf', 100, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('side');
    }

    public function test_tenant_document_type_is_restricted_to_its_own_enum(): void
    {
        Storage::fake('public');
        $flat = Flat::factory()->create();
        $tenant = Tenant::factory()->for(Person::factory())->create(['flat_id' => $flat->id]);

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson("/api/masters/tenants/{$tenant->id}/documents", [
            'document_type' => 'NAMJARI', // valid for owners, not tenants
            'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('document_type');
    }
}
