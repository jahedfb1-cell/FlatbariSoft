<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Contribution;
use App\Models\ContributionHead;
use App\Models\Flat;
use App\Models\User;
use App\Services\ContributionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CommunityHallSeeder;
use Database\Seeders\ContributionSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleCatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The office adds, renames, switches off and deletes the heads money is collected for; one payment can cover several heads. */
class ContributionHeadsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ContributionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, CommunityHallSeeder::class, RoleCatalogSeeder::class, ContributionSeeder::class] as $seeder) {
            $this->seed($seeder);
        }
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->service = app(ContributionService::class);
    }

    private function api()
    {
        return $this->actingAs($this->admin, 'sanctum');
    }

    private function headId(string $code): int
    {
        return ContributionHead::where('code', $code)->value('id');
    }

    public function test_a_new_head_can_be_added_and_is_offered_for_payment_at_once(): void
    {
        $this->api()->postJson('/api/contributions/books/CONSTRUCTION/heads', ['name_bn' => 'লিফট ফি', 'name_en' => 'Lift fee'])->assertOk();

        $head = ContributionHead::where('name_en', 'Lift fee')->firstOrFail();
        $this->assertSame('LIFT_FEE', $head->code);
        $this->assertTrue($head->counts_toward_target);
        $this->assertContains($head->id, collect($this->api()->getJson('/api/contributions/books')->json())->firstWhere('code', 'CONSTRUCTION')['heads'] ? collect(collect($this->api()->getJson('/api/contributions/books')->json())->firstWhere('code', 'CONSTRUCTION')['heads'])->pluck('id')->all() : []);
        $this->assertSame(1, AuditLog::where('event', 'head_created')->count());

        // the same name again is refused
        $this->api()->postJson('/api/contributions/books/CONSTRUCTION/heads', ['name_bn' => 'লিফট ফি'])->assertStatus(422)->assertJsonValidationErrors('name_bn');
    }

    public function test_one_payment_can_cover_several_heads_and_all_or_none_is_saved(): void
    {
        $flat = Flat::factory()->create(['flat_no' => '10A']);
        $body = ['received_on' => '2026-09-01', 'method' => 'CASH', 'lines' => [
            ['contribution_head_id' => $this->headId('SUBSCRIPTION'), 'amount' => 50000],
            ['contribution_head_id' => $this->headId('GAS_LINE'), 'amount' => 30000],
            ['contribution_head_id' => $this->headId('GENERATOR'), 'amount' => 0], // nothing for this one: skipped
        ]];

        $this->api()->postJson("/api/contributions/flats/{$flat->id}/collections", $body)->assertCreated();

        $this->assertSame(2, Contribution::where('flat_id', $flat->id)->count());
        $this->assertSame('80000.0000', (string) Contribution::where('flat_id', $flat->id)->sum('amount') . '.0000');

        // a payment with nothing in it saves nothing
        $this->api()->postJson("/api/contributions/flats/{$flat->id}/collections", ['received_on' => '2026-09-02', 'method' => 'CASH', 'lines' => [['contribution_head_id' => $this->headId('GAS_LINE'), 'amount' => 0]]])
            ->assertStatus(422);
        $this->assertSame(2, Contribution::count());
    }

    public function test_the_receipt_of_a_multi_head_payment_is_one_pdf(): void
    {
        $flat = Flat::factory()->create();
        $rows = $this->service->recordMany($flat, ['received_on' => '2026-09-01', 'method' => 'CASH', 'lines' => [
            ['contribution_head_id' => $this->headId('SUBSCRIPTION'), 'amount' => 50000],
            ['contribution_head_id' => $this->headId('EAST_WALL'), 'amount' => 65000],
        ]], $this->admin->id);

        $res = $this->api()->get('/api/contributions/receipt?'.http_build_query(['ids' => $rows->pluck('id')->all()]))->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());

        // rows of two different flats cannot share a receipt
        $other = $this->service->record(Flat::factory()->create(), ['contribution_head_id' => $this->headId('SUBSCRIPTION'), 'amount' => 1000, 'received_on' => '2026-09-01', 'method' => 'CASH'], $this->admin->id);
        $this->api()->getJson('/api/contributions/receipt?'.http_build_query(['ids' => [$rows->first()->id, $other->id]]))->assertStatus(422);
    }

    public function test_a_head_with_money_under_it_cannot_be_deleted_but_can_be_switched_off_and_still_shows(): void
    {
        $flat = Flat::factory()->create(['flat_no' => '10A']);
        $this->service->record($flat, ['contribution_head_id' => $this->headId('EAST_WALL'), 'amount' => 65000, 'received_on' => '2026-09-01', 'method' => 'CASH'], $this->admin->id);
        $wall = ContributionHead::where('code', 'EAST_WALL')->firstOrFail();

        $this->api()->deleteJson("/api/contributions/heads/{$wall->id}")->assertStatus(422)->assertJsonValidationErrors('head');
        $this->assertNotNull($wall->fresh());

        $this->api()->putJson("/api/contributions/heads/{$wall->id}", ['is_active' => false])->assertOk();

        // no new payment can go to it ...
        $this->api()->postJson("/api/contributions/flats/{$flat->id}/collections", ['contribution_head_id' => $wall->id, 'amount' => 10, 'received_on' => '2026-09-02', 'method' => 'CASH'])->assertStatus(404);
        // ... but its money is still in the sheet, so the balances do not move
        $statement = $this->api()->getJson('/api/contributions/books/CONSTRUCTION/statement')->json();
        $this->assertContains('EAST_WALL', collect($statement['heads'])->pluck('code')->all());
        $this->assertSame('65000.0000', collect($statement['rows'])->firstWhere('flat_no', '10A')['collected']);
    }

    public function test_an_unused_head_can_be_deleted_and_the_deletion_is_logged(): void
    {
        $this->api()->postJson('/api/contributions/books/CONSTRUCTION/heads', ['name_bn' => 'টেস্ট', 'name_en' => 'Test head'])->assertOk();
        $head = ContributionHead::where('name_en', 'Test head')->firstOrFail();

        $this->api()->deleteJson("/api/contributions/heads/{$head->id}")->assertOk();

        $this->assertNull(ContributionHead::find($head->id));
        $this->assertSame(1, AuditLog::where('event', 'head_deleted')->count());
    }

    public function test_counting_toward_the_target_cannot_change_once_money_was_collected(): void
    {
        $this->service->record(Flat::factory()->create(), ['contribution_head_id' => $this->headId('GENERATOR'), 'amount' => 10000, 'received_on' => '2026-09-01', 'method' => 'CASH'], $this->admin->id);
        $generator = ContributionHead::where('code', 'GENERATOR')->firstOrFail();

        $this->api()->putJson("/api/contributions/heads/{$generator->id}", ['counts_toward_target' => false])->assertStatus(422)->assertJsonValidationErrors('counts_toward_target');
        $this->api()->putJson("/api/contributions/heads/{$generator->id}", ['name_bn' => 'জেনারেটর (নতুন)'])->assertOk();
        $this->assertSame('জেনারেটর (নতুন)', $generator->fresh()->name_bn);
    }

    public function test_the_last_active_head_of_a_book_stays_and_only_managers_edit_heads(): void
    {
        $only = ContributionHead::where('code', 'FLAT_PRICE')->firstOrFail();
        $this->api()->deleteJson("/api/contributions/heads/{$only->id}")->assertStatus(422);
        $this->api()->putJson("/api/contributions/heads/{$only->id}", ['is_active' => false])->assertStatus(422);

        $treasurer = User::factory()->create(['role' => 'committee_treasurer']);
        $treasurer->assignRole('committee_treasurer'); // may collect, may not manage
        $this->actingAs($treasurer, 'sanctum')->postJson('/api/contributions/books/CONSTRUCTION/heads', ['name_bn' => 'এক্স'])->assertForbidden();
        $this->actingAs($treasurer, 'sanctum')->getJson('/api/contributions/books/CONSTRUCTION/heads')->assertOk();
    }
}
