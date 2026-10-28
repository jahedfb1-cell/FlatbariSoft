<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\Resolution;
use App\Models\User;
use Database\Seeders\GovernancePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A resolution is text, a PDF, a photo of the minutes - or text with a file. */
class ResolutionAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $this->seed(GovernancePermissionSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->meeting = Meeting::create(['title' => 'AGM', 'meeting_date' => '2026-10-30', 'status' => 'SCHEDULED']);
    }

    private function addRes(array $data)
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson("/api/meetings/{$this->meeting->id}/resolutions", $data);
    }

    public function test_text_only_resolution_still_works(): void
    {
        $this->addRes(['description' => 'Repaint the lobby'])->assertCreated()->assertJsonPath('has_attachment', false);
    }

    public function test_a_pdf_alone_is_enough(): void
    {
        $res = $this->addRes(['attachment' => UploadedFile::fake()->create('minutes.pdf', 200, 'application/pdf')])
            ->assertCreated()->assertJsonPath('has_attachment', true)->assertJsonPath('attachment_name', 'minutes.pdf');

        // the storage path is never sent to the browser
        $this->assertArrayNotHasKey('attachment_path', $res->json());
        Storage::disk('local')->assertExists(Resolution::find($res->json('id'))->attachment_path);
    }

    public function test_a_photo_with_text_is_accepted(): void
    {
        $this->addRes(['description' => 'Signed copy', 'attachment' => UploadedFile::fake()->image('minutes.jpg')])
            ->assertCreated()->assertJsonPath('description', 'Signed copy')->assertJsonPath('has_attachment', true);
    }

    public function test_nothing_at_all_is_rejected(): void
    {
        $this->addRes([])->assertStatus(422)->assertJsonValidationErrors('description');
    }

    public function test_other_file_types_and_huge_files_are_rejected(): void
    {
        $this->addRes(['attachment' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])->assertStatus(422)->assertJsonValidationErrors('attachment');
        $this->addRes(['attachment' => UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf')])->assertStatus(422)->assertJsonValidationErrors('attachment');
    }

    public function test_the_file_can_be_fetched_and_replaced_by_authorised_users_only(): void
    {
        $id = $this->addRes(['attachment' => UploadedFile::fake()->create('v1.pdf', 50, 'application/pdf')])->json('id');
        $old = Resolution::find($id)->attachment_path;

        $this->actingAs($this->admin, 'sanctum')->get("/api/meetings/resolutions/{$id}/attachment")->assertOk();

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/meetings/resolutions/{$id}/attachment", [
            'attachment' => UploadedFile::fake()->image('v2.png'),
        ])->assertOk()->assertJsonPath('attachment_name', 'v2.png');
        Storage::disk('local')->assertMissing($old);

        $nobody = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($nobody, 'sanctum')->get("/api/meetings/resolutions/{$id}/attachment")->assertForbidden();
        $this->actingAs($nobody, 'sanctum')->postJson("/api/meetings/resolutions/{$id}/attachment", [])->assertForbidden();
    }

    public function test_a_resolution_without_a_file_has_nothing_to_fetch(): void
    {
        $id = $this->addRes(['description' => 'Text only'])->json('id');
        $this->actingAs($this->admin, 'sanctum')->getJson("/api/meetings/resolutions/{$id}/attachment")->assertNotFound();
    }
}
