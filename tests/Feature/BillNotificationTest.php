<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\Person;
use App\Models\User;
use App\Services\BillGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** When the month's bills are generated, the people of each flat are told in the app. */
class BillNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CollectionHeadSeeder::class);
    }

    private function resident(Flat $flat, string $role = 'OWNER'): User
    {
        $person = Person::factory()->create();
        FlatParty::create(['flat_id' => $flat->id, 'person_id' => $person->id, 'role' => $role, 'from_date' => '2026-01-01']);

        return User::factory()->create(['person_id' => $person->id, 'role' => 'flat_owner']);
    }

    public function test_each_flats_people_get_their_own_bill_notification_and_nobody_else(): void
    {
        $office = User::factory()->create();
        $a = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 3500]);
        $b = Flat::factory()->create(['occupancy_status' => 'OWNER_OCCUPIED', 'service_charge_rate' => 4000]);
        $ownerA = $this->resident($a);
        $tenantA = $this->resident($a, 'TENANT');
        $ownerB = $this->resident($b);

        app(BillGenerationService::class)->generate(2026, 8, $office->id);

        $this->assertCount(1, $ownerA->notifications);
        $this->assertCount(1, $tenantA->notifications);
        $this->assertCount(1, $ownerB->notifications);
        $this->assertCount(0, $office->notifications);

        $data = $ownerA->notifications->first()->data;
        $this->assertSame('BILL', $data['kind']);
        $this->assertStringContainsString('আগস্ট 2026', $data['title']);
        $this->assertStringContainsString('3,500.00', $data['body']);
        $this->assertSame(Bill::where('flat_id', $a->id)->value('id'), $data['bill_id']);
        $this->assertStringContainsString('4,000.00', $ownerB->notifications->first()->data['body']);
    }

    public function test_a_person_who_left_the_flat_is_not_told(): void
    {
        $office = User::factory()->create();
        $flat = Flat::factory()->create();
        $gone = $this->resident($flat, 'TENANT');
        FlatParty::where('person_id', $gone->person_id)->update(['to_date' => '2026-06-30']);

        app(BillGenerationService::class)->generate(2026, 8, $office->id);

        $this->assertCount(0, $gone->notifications);
    }

    public function test_a_resident_reads_marks_and_cannot_touch_someone_elses_notifications(): void
    {
        $office = User::factory()->create();
        $flat = Flat::factory()->create();
        $me = $this->resident($flat);
        $other = $this->resident(Flat::factory()->create());
        app(BillGenerationService::class)->generate(2026, 8, $office->id);

        $api = $this->actingAs($me, 'sanctum');
        $list = $api->getJson('/api/me/notifications')->assertOk();
        $this->assertSame(1, $list->json('unread'));
        $id = $list->json('items.0.id');
        $this->assertFalse($list->json('items.0.read'));

        $this->actingAs($other, 'sanctum')->postJson("/api/me/notifications/{$id}/read")->assertNotFound();
        $this->assertSame(1, $this->actingAs($me, 'sanctum')->getJson('/api/me/notifications')->json('unread'));

        $this->actingAs($me, 'sanctum')->postJson("/api/me/notifications/{$id}/read")->assertOk()->assertJsonPath('unread', 0);
        $this->assertTrue($this->actingAs($me, 'sanctum')->getJson('/api/me/notifications')->json('items.0.read'));
    }

    public function test_read_all_clears_the_count(): void
    {
        $office = User::factory()->create();
        $me = $this->resident(Flat::factory()->create());
        app(BillGenerationService::class)->generate(2026, 8, $office->id);
        app(BillGenerationService::class)->generate(2026, 9, $office->id);

        $api = $this->actingAs($me, 'sanctum');
        $this->assertSame(2, $api->getJson('/api/me/notifications')->json('unread'));
        $api->postJson('/api/me/notifications/read-all')->assertOk();
        $this->assertSame(0, $api->getJson('/api/me/notifications')->json('unread'));
    }

    public function test_notifications_need_a_login(): void
    {
        $this->getJson('/api/me/notifications')->assertUnauthorized();
    }
}
