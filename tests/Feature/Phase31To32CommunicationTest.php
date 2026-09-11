<?php

namespace Tests\Feature;

use App\Models\SmsLog;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase31To32CommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_is_logged_and_stays_queued_without_a_real_gateway(): void
    {
        $this->seed(\Database\Seeders\SmsTemplateSeeder::class);

        $log = app(SmsService::class)->sendFromTemplate('PAYMENT_RECEIVED', '01711112222', [
            'flat_no' => 'A-101', 'amount' => '3500', 'receipt_no' => 'MR-000001',
        ]);

        $log->refresh();
        $this->assertSame('QUEUED_PENDING_GATEWAY', $log->status);
        $this->assertStringContainsString('A-101', $log->message);
        $this->assertStringContainsString('3500', $log->message);
        $this->assertStringContainsString('MR-000001', $log->message);
        $this->assertDatabaseHas('sms_logs', ['id' => $log->id, 'mobile' => '01711112222']);
    }

    public function test_sms_without_a_matching_template_still_logs_something(): void
    {
        $log = app(SmsService::class)->sendFromTemplate('UNKNOWN_EVENT', '01711112222');

        $this->assertNotNull($log->id);
        $this->assertSame('QUEUED_PENDING_GATEWAY', $log->status);
    }

    public function test_admin_can_publish_a_notice_and_it_is_visible_to_any_logged_in_user(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\CommunicationPermissionSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $owner = User::factory()->create(['role' => 'flat_owner']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/notices', [
            'title' => 'AGM Notice', 'body' => 'Annual general meeting on 2026-09-01.',
        ])->assertStatus(201);

        $this->actingAs($owner, 'sanctum')->getJson('/api/notices')
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'AGM Notice']);
    }

    public function test_manager_cannot_publish_a_notice(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\CommunicationPermissionSeeder::class);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->assignRole('manager');

        $this->actingAs($manager, 'sanctum')->postJson('/api/notices', [
            'title' => 'Test', 'body' => 'Test body',
        ])->assertStatus(403);
    }
}
