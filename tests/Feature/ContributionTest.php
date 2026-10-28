<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Contribution;
use App\Models\ContributionHead;
use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\JournalLine;
use App\Models\Person;
use App\Models\User;
use App\Services\ContributionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CommunityHallSeeder;
use Database\Seeders\ContributionSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleCatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Construction-cost collection: targets, receipts, opening balances, reversals and the per-owner ledger. */
class ContributionTest extends TestCase
{
    use RefreshDatabase;

    private ContributionService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, CommunityHallSeeder::class, RoleCatalogSeeder::class, ContributionSeeder::class] as $seeder) {
            $this->seed($seeder);
        }
        $this->service = app(ContributionService::class);
        $this->admin = $this->user('admin');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    private function headId(string $code): int
    {
        return ContributionHead::where('code', $code)->value('id');
    }

    private function flat(string $no = '10A'): Flat
    {
        return Flat::factory()->create(['flat_no' => $no]);
    }

    private function collect(Flat $flat, string $head, string $amount, string $date = '2026-09-01', string $method = 'CASH'): Contribution
    {
        return $this->service->record($flat, ['contribution_head_id' => $this->headId($head), 'amount' => $amount, 'received_on' => $date, 'method' => $method], $this->admin->id);
    }

    // ---- targets

    public function test_default_target_is_the_committees_figure_and_a_flat_can_have_its_own(): void
    {
        $flat = $this->flat();

        $this->assertSame('2550000.0000', $this->service->targetFor($flat));

        $this->service->setFlatTarget($flat, '2950000', 'held for sale');
        $this->assertSame('2950000.0000', $this->service->targetFor($flat));

        $this->service->setFlatTarget($flat, null); // back to the default
        $this->assertSame('2550000.0000', $this->service->targetFor($flat));

        $this->service->setDefaultTarget('2600000');
        $this->assertSame('2600000.0000', $this->service->targetFor($flat));
    }

    public function test_every_head_of_the_construction_cost_is_seeded(): void
    {
        $codes = ContributionHead::orderBy('sort_order')->pluck('code')->all();

        $this->assertSame(['SUBSCRIPTION', 'GAS_LINE', 'GENERATOR', 'WATER_DUES', 'EAST_WALL'], $codes);
    }

    // ---- receipts and the books

    public function test_a_receipt_gets_a_number_and_posts_cash_and_a_balanced_journal_to_the_capital_fund(): void
    {
        $row = $this->collect($this->flat(), 'SUBSCRIPTION', '100000');

        $this->assertSame('CC-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT), $row->receipt_no);
        $this->assertDatabaseHas('cash_ledger', ['in_amount' => 100000, 'fund_type' => 'CAPITAL']);

        $lines = JournalLine::where('journal_entry_id', $row->journal_entry_id)->get();
        $this->assertEquals($lines->sum('debit'), $lines->sum('credit'));
        $capital = ChartOfAccount::where('code', '3200')->first();
        $this->assertSame(100000.0, (float) $lines->firstWhere('account_id', $capital->id)->credit);
        $this->assertNull($lines->firstWhere('account_id', ChartOfAccount::where('code', '4100')->first()->id)); // not income
    }

    public function test_bank_receipts_go_to_the_bank_ledger(): void
    {
        \App\Models\BankAccount::create(['bank_name' => 'Test', 'account_title' => 'GV', 'account_no' => '1', 'opening_balance' => 0, 'current_balance' => 0]);

        $this->collect($this->flat(), 'GAS_LINE', '30000', '2026-09-02', 'BANK');

        $this->assertDatabaseHas('bank_transactions', ['debit' => 30000, 'fund_type' => 'CAPITAL']);
    }

    public function test_zero_and_negative_amounts_are_refused(): void
    {
        $this->expectException(ValidationException::class);
        $this->collect($this->flat(), 'SUBSCRIPTION', '0');
    }

    // ---- the statement

    public function test_statement_adds_the_heads_and_derives_due_and_excess(): void
    {
        $a = $this->flat('10A');
        $b = $this->flat('9A');
        // 10A: 24,50,000 + 30,000 + 10,000 + 10,000 + 65,000 = 25,65,000 -> paid 15,000 over the 25,50,000 target
        foreach ([['SUBSCRIPTION', '2450000'], ['GAS_LINE', '30000'], ['GENERATOR', '10000'], ['WATER_DUES', '10000'], ['EAST_WALL', '65000']] as [$h, $amt]) {
            $this->service->importOpening([['flat_no' => '10A', 'amounts' => [$h => $amt]]], '2026-01-01', $this->admin->id);
        }
        // 9A: 24,00,000 + the same 1,15,000 = 25,15,000 -> 35,000 still due
        $this->service->importOpening([['flat_no' => '9A', 'amounts' => ['SUBSCRIPTION' => 2400000, 'GAS_LINE' => 30000, 'GENERATOR' => 10000, 'WATER_DUES' => 10000, 'EAST_WALL' => 65000]]], '2026-01-01', $this->admin->id);

        $s = $this->service->statement();
        $rows = collect($s['rows'])->keyBy('flat_no');

        $this->assertSame('2565000.0000', $rows['10A']['collected']);
        $this->assertSame('-15000.0000', $rows['10A']['balance']);
        $this->assertSame('EXCESS', $rows['10A']['status']);
        $this->assertSame('2515000.0000', $rows['9A']['collected']);
        $this->assertSame('35000.0000', $rows['9A']['balance']);
        $this->assertSame('DUE', $rows['9A']['status']);
        $this->assertSame('4850000.0000', $s['totals']['SUBSCRIPTION']);
        $this->assertSame('35000.0000', $s['totals']['balance_due']);
        $this->assertSame('15000.0000', $s['totals']['excess']);
        // top floor first, as on the manager's sheet
        $this->assertSame(['10A', '9A'], array_column($s['rows'], 'flat_no'));
    }

    public function test_a_head_that_does_not_count_toward_the_target_is_shown_but_not_subtracted(): void
    {
        ContributionHead::where('code', 'GENERATOR')->update(['counts_toward_target' => false]);
        $flat = $this->flat();
        $this->collect($flat, 'SUBSCRIPTION', '1000000');
        $this->collect($flat, 'GENERATOR', '10000');

        $row = $this->service->statement()['rows'][0];

        $this->assertSame('10000.0000', $row['heads']['GENERATOR']);
        $this->assertSame('1000000.0000', $row['collected']);
        $this->assertSame('1550000.0000', $row['balance']);
    }

    public function test_a_flats_own_target_changes_its_due(): void
    {
        $flat = $this->flat('4C');
        $this->service->setFlatTarget($flat, '2950000');
        $this->collect($flat, 'SUBSCRIPTION', '2640000');

        $row = $this->service->statement()['rows'][0];

        $this->assertSame('310000.0000', $row['balance']);
        $this->assertTrue($row['has_own_target']);
    }

    // ---- loading earlier collections

    public function test_opening_rows_move_the_balance_but_never_touch_cash_bank_or_the_journal(): void
    {
        $this->flat('10A');

        $r = $this->service->importOpening([['flat_no' => '10A', 'amounts' => ['SUBSCRIPTION' => 2450000, 'EAST_WALL' => 65000]]], '2026-01-01', $this->admin->id);

        $this->assertSame(2, $r['entries']);
        $this->assertSame('2515000.0000', $r['total']);
        $this->assertDatabaseCount('cash_ledger', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertSame(2, Contribution::where('method', 'OPENING')->whereNull('journal_entry_id')->count());
        $this->assertSame('35000.0000', $this->service->statement()['rows'][0]['balance']);
    }

    public function test_a_dry_run_saves_nothing(): void
    {
        $this->flat('10A');

        $r = $this->service->importOpening([['flat_no' => '10A', 'amounts' => ['SUBSCRIPTION' => 100]]], '2026-01-01', $this->admin->id, dryRun: true);

        $this->assertSame(1, $r['entries']);
        $this->assertDatabaseCount('contributions', 0);
    }

    public function test_one_bad_row_rejects_the_whole_import(): void
    {
        $this->flat('10A');

        try {
            $this->service->importOpening([
                ['flat_no' => '10A', 'amounts' => ['SUBSCRIPTION' => 100]],
                ['flat_no' => '99Z', 'amounts' => ['SUBSCRIPTION' => 100]],
                ['flat_no' => '10A', 'amounts' => ['NOT_A_HEAD' => 100]],
            ], '2026-01-01', $this->admin->id);
            $this->fail('must be refused');
        } catch (ValidationException $e) {
            $this->assertCount(2, $e->errors()['rows']);
        }

        $this->assertDatabaseCount('contributions', 0); // nothing from the good row was kept
    }

    // ---- reversals

    public function test_reversing_a_receipt_adds_an_opposite_row_and_opposite_ledger_lines(): void
    {
        $flat = $this->flat();
        $row = $this->collect($flat, 'SUBSCRIPTION', '50000');

        $rev = $this->service->reverse($row, 'ভুল এন্ট্রি', $this->admin->id);

        $this->assertSame('-50000.0000', $rev->amount);
        $this->assertSame($row->id, $rev->reverses_id);
        $this->assertDatabaseHas('cash_ledger', ['out_amount' => 50000]);
        $this->assertSame('2550000.0000', $this->service->statement()['rows'][0]['balance']); // back to the full target
        $this->assertDatabaseHas('contributions', ['id' => $row->id, 'amount' => 50000]); // the original is untouched

        $lines = JournalLine::where('journal_entry_id', $rev->journal_entry_id)->get();
        $this->assertEquals($lines->sum('debit'), $lines->sum('credit'));
    }

    public function test_a_receipt_cannot_be_reversed_twice_and_a_reversal_cannot_be_reversed(): void
    {
        $row = $this->collect($this->flat(), 'SUBSCRIPTION', '50000');
        $rev = $this->service->reverse($row, 'x', $this->admin->id);

        try {
            $this->service->reverse($row, 'again', $this->admin->id);
            $this->fail('second reversal must be refused');
        } catch (ValidationException) {
        }

        $this->expectException(ValidationException::class);
        $this->service->reverse($rev, 'undo the undo', $this->admin->id);
    }

    public function test_reversing_an_opening_row_has_no_ledger_lines_to_undo(): void
    {
        $this->flat('10A');
        $this->service->importOpening([['flat_no' => '10A', 'amounts' => ['SUBSCRIPTION' => 100000]]], '2026-01-01', $this->admin->id);

        $this->service->reverse(Contribution::first(), 'entered twice', $this->admin->id);

        $this->assertDatabaseCount('cash_ledger', 0);
        $this->assertSame('2550000.0000', $this->service->statement()['rows'][0]['balance']);
    }

    // ---- the owner's ledger

    public function test_the_ledger_lists_receipts_in_date_order_with_the_balance_after_each(): void
    {
        $flat = $this->flat('10A');
        $this->collect($flat, 'EAST_WALL', '65000', '2026-03-01');
        $this->collect($flat, 'SUBSCRIPTION', '2400000', '2026-01-15');
        $wrong = $this->collect($flat, 'GAS_LINE', '30000', '2026-05-01');
        $this->service->reverse($wrong, 'wrong flat', $this->admin->id);

        $l = $this->service->ledger($flat);

        $this->assertSame('2550000.0000', $l['target']);
        $this->assertSame(['2026-01-15', '2026-03-01', '2026-05-01'], array_slice(array_column($l['entries'], 'date'), 0, 3));
        $this->assertSame('150000.0000', $l['entries'][0]['balance_after']);   // 25,50,000 - 24,00,000
        $this->assertSame('85000.0000', $l['entries'][1]['balance_after']);    // - 65,000 wall
        $this->assertSame('55000.0000', $l['entries'][2]['balance_after']);    // - 30,000 gas
        $this->assertSame('85000.0000', $l['balance']);                        // gas receipt reversed again
        $this->assertTrue($l['entries'][2]['is_reversed']);
        $this->assertTrue(end($l['entries'])['is_reversal']);
    }

    public function test_the_ledger_names_the_current_owner(): void
    {
        $flat = $this->flat('10A');
        $person = Person::factory()->create(['name_en' => 'NAZRUL ISLAM']);
        FlatParty::create(['flat_id' => $flat->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2022-01-01']);

        $this->assertSame('NAZRUL ISLAM', $this->service->ledger($flat)['owner']);
    }

    // ---- HTTP, permissions and the owner portal

    public function test_roles_get_the_right_slice(): void
    {
        $flat = $this->flat();
        $body = ['contribution_head_id' => $this->headId('SUBSCRIPTION'), 'amount' => 1000, 'received_on' => '2026-09-01', 'method' => 'CASH'];

        $member = $this->user('committee_member');
        $treasurer = $this->user('committee_treasurer');
        $president = $this->user('committee_president');

        $this->actingAs($member, 'sanctum')->getJson('/api/contributions/statement')->assertOk();
        $this->actingAs($member, 'sanctum')->postJson("/api/contributions/flats/{$flat->id}/collections", $body)->assertForbidden();
        $this->actingAs($treasurer, 'sanctum')->postJson("/api/contributions/flats/{$flat->id}/collections", $body)->assertCreated();
        $this->actingAs($treasurer, 'sanctum')->putJson("/api/contributions/flats/{$flat->id}/target", ['target_amount' => 1])->assertForbidden();
        $this->actingAs($president, 'sanctum')->putJson("/api/contributions/flats/{$flat->id}/target", ['target_amount' => 2900000])->assertOk()->assertJsonPath('target', '2900000.0000');
        $this->actingAs($president, 'sanctum')->postJson("/api/contributions/flats/{$flat->id}/collections", $body)->assertForbidden();
    }

    public function test_default_target_import_and_reversal_over_http(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $flat = $this->flat('10A');

        $this->putJson('/api/contributions/settings/default-target', ['target_amount' => 2600000])->assertOk()->assertJsonPath('default_target', '2600000.0000');

        $this->postJson('/api/contributions/import', ['as_of' => '2026-01-01', 'dry_run' => true, 'rows' => [['flat_no' => '10A', 'amounts' => ['SUBSCRIPTION' => 2000000]]]])
            ->assertOk()->assertJsonPath('entries', 1);
        $this->assertDatabaseCount('contributions', 0);
        $this->postJson('/api/contributions/import', ['as_of' => '2026-01-01', 'rows' => [['flat_no' => '10A', 'amounts' => ['SUBSCRIPTION' => 2000000]]]])->assertOk();
        $this->postJson('/api/contributions/import', ['as_of' => '2026-01-01', 'rows' => [['flat_no' => 'NOPE', 'amounts' => ['SUBSCRIPTION' => 1]]]])->assertStatus(422);

        $id = Contribution::first()->id;
        $this->postJson("/api/contributions/collections/{$id}/reverse", ['reason' => 'test'])->assertOk();
        $this->postJson("/api/contributions/collections/{$id}/reverse", ['reason' => 'again'])->assertStatus(422);
        $this->getJson("/api/contributions/flats/{$flat->id}/ledger")->assertOk()->assertJsonPath('balance', '2600000.0000');
    }

    public function test_pdf_and_excel_exports_work(): void
    {
        $flat = $this->flat('10A');
        $this->collect($flat, 'SUBSCRIPTION', '100000');
        $this->flat('9A');
        $this->actingAs($this->admin, 'sanctum');

        foreach (['/api/contributions/statement/pdf', "/api/contributions/flats/{$flat->id}/ledger/pdf", '/api/contributions/ledgers/pdf'] as $url) {
            $res = $this->get($url)->assertOk();
            $this->assertStringStartsWith('%PDF', $res->getContent());
        }
        $this->get('/api/contributions/statement/excel')->assertOk();
    }

    public function test_an_owner_sees_only_their_own_ledger_and_a_tenant_sees_none(): void
    {
        $person = Person::factory()->create();
        $owner = User::factory()->create(['person_id' => $person->id, 'role' => 'flat_owner']);
        $owner->assignRole('flat_owner');
        $mine = $this->flat('10A');
        $other = $this->flat('9A');
        FlatParty::create(['flat_id' => $mine->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2022-01-01']);
        $this->collect($mine, 'SUBSCRIPTION', '100000');
        $this->collect($other, 'SUBSCRIPTION', '555000');

        $res = $this->actingAs($owner, 'sanctum')->getJson('/api/me/contributions')->assertOk();
        $this->assertSame(['10A'], array_column($res->json(), 'flat_no'));
        $this->assertSame('100000.0000', $res->json('0.collected'));
        $this->actingAs($owner, 'sanctum')->get('/api/me/contributions/pdf')->assertOk();

        // a tenant of the same flat has no share in the construction cost
        $tp = Person::factory()->create();
        $tenant = User::factory()->create(['person_id' => $tp->id, 'role' => 'tenant']);
        $tenant->assignRole('tenant');
        FlatParty::create(['flat_id' => $mine->id, 'person_id' => $tp->id, 'role' => 'TENANT', 'from_date' => '2025-01-01']);
        $this->actingAs($tenant, 'sanctum')->getJson('/api/me/contributions')->assertOk()->assertExactJson([]);
        $this->actingAs($tenant, 'sanctum')->get('/api/me/contributions/pdf')->assertNotFound();

        // and neither can reach the office endpoints
        $this->actingAs($owner, 'sanctum')->getJson('/api/contributions/statement')->assertForbidden();
        $this->actingAs($owner, 'sanctum')->getJson("/api/contributions/flats/{$other->id}/ledger")->assertForbidden();
    }
}
