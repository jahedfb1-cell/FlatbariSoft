<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ContributionSeeder;
use Database\Seeders\ExpenseHeadSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\OperationsPermissionSeeder;
use Database\Seeders\RoleCatalogSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TreasurySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A one-off work: the split is picked, every share is adjustable, collections and expenses are read side by side. */
class WorkBudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var list<Flat> */
    private array $flats;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, ExpenseHeadSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, RoleCatalogSeeder::class, OperationsPermissionSeeder::class, TreasurySeeder::class, ContributionSeeder::class] as $s) {
            $this->seed($s);
        }
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->flats = [
            Flat::factory()->create(['flat_no' => 'A1', 'service_charge_rate' => 2000, 'size_sqft' => 1000]),
            Flat::factory()->create(['flat_no' => 'A2', 'service_charge_rate' => 3000, 'size_sqft' => 1500]),
            Flat::factory()->create(['flat_no' => 'A3', 'service_charge_rate' => 5000, 'size_sqft' => 2500]),
        ];
    }

    private function create(array $over = [], ?array $shares = null)
    {
        $shares ??= [['flat_id' => $this->flats[0]->id, 'amount' => 30000], ['flat_id' => $this->flats[1]->id, 'amount' => 70000]];

        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/work-budgets', $over + [
            'title' => 'Roof repair', 'estimated_cost' => 100000, 'split_method' => 'MANUAL', 'shares' => $shares,
        ]);
    }

    public function test_the_split_methods_propose_shares_that_add_up_to_the_estimate(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $ids = collect($this->flats)->pluck('id')->all();

        $equal = $api->postJson('/api/work-budgets/preview', ['split_method' => 'EQUAL', 'estimated_cost' => 100000, 'flat_ids' => $ids])->assertOk()->json('rows');
        $this->assertSame('100000.00', array_reduce($equal, fn ($c, $r) => bcadd($c, $r['amount'], 2), '0'));
        $this->assertSame('33333.33', $equal[0]['amount']);

        $byRate = $api->postJson('/api/work-budgets/preview', ['split_method' => 'SERVICE_RATE', 'estimated_cost' => 100000, 'flat_ids' => $ids])->json('rows');
        $this->assertSame(['20000.00', '30000.00', '50000.00'], array_column($byRate, 'amount')); // 2000 : 3000 : 5000

        $bySize = $api->postJson('/api/work-budgets/preview', ['split_method' => 'SIZE', 'estimated_cost' => 100000, 'flat_ids' => [$ids[0], $ids[2]]])->json('rows');
        $this->assertCount(2, $bySize); // only the chosen flats take part
        $this->assertSame(['28571.42', '71428.58'], array_column($bySize, 'amount')); // rounded down, the remainder goes to the last flat

        $manual = $api->postJson('/api/work-budgets/preview', ['split_method' => 'MANUAL', 'estimated_cost' => 100000])->json('rows');
        $this->assertCount(3, $manual);
        $this->assertSame('0.00', $manual[0]['amount']);
    }

    public function test_a_work_is_created_with_hand_set_shares_and_reports_the_gap(): void
    {
        $res = $this->create()->assertCreated();

        $res->assertJsonPath('planned', '100000.0000')->assertJsonPath('gap', '0.0000')->assertJsonPath('flats', 2);
        $this->assertSame(['30000.0000', '70000.0000'], array_column($res->json('rows'), 'share'));

        // under-asked: shares do not cover the estimate
        $this->create(['title' => 'Paint'], [['flat_id' => $this->flats[0]->id, 'amount' => 20000]])->assertJsonPath('gap', '80000.0000');
    }

    public function test_shares_can_be_changed_and_a_flat_taken_off(): void
    {
        $id = $this->create()->json('id');
        $api = $this->actingAs($this->admin, 'sanctum');

        $r = $api->putJson("/api/work-budgets/{$id}/shares", ['shares' => [
            ['flat_id' => $this->flats[0]->id, 'amount' => 0, 'note' => 'waived by the committee'],
            ['flat_id' => $this->flats[2]->id, 'amount' => 60000],
        ]])->assertOk();

        $this->assertSame(2, $r->json('flats'));
        $this->assertSame('60000.0000', $r->json('planned'));
        $this->assertNotContains($this->flats[1]->id, array_column($r->json('rows'), 'flat_id')); // taken off
    }

    public function test_editing_only_the_amount_keeps_the_waiver_reason(): void
    {
        $id = $this->create()->json('id');
        $api = $this->actingAs($this->admin, 'sanctum');
        $flat = $this->flats[0]->id;

        $api->putJson("/api/work-budgets/{$id}/shares", ['shares' => [['flat_id' => $flat, 'amount' => 0, 'note' => 'waived by the committee']]])->assertOk();
        // the detail screen sends amounts only - the reason written earlier must survive
        $api->putJson("/api/work-budgets/{$id}/shares", ['shares' => [['flat_id' => $flat, 'amount' => 5000]]])->assertOk();

        $this->assertSame('waived by the committee', \App\Models\FlatContributionTarget::where('flat_id', $flat)->value('note'));
        // and a note sent on purpose still replaces it, or clears it
        $api->putJson("/api/work-budgets/{$id}/shares", ['shares' => [['flat_id' => $flat, 'amount' => 5000, 'note' => null]]])->assertOk();
        $this->assertNull(\App\Models\FlatContributionTarget::where('flat_id', $flat)->value('note'));
    }

    public function test_collections_and_expenses_are_read_side_by_side(): void
    {
        $work = $this->create()->json();
        $api = $this->actingAs($this->admin, 'sanctum');

        // a flat pays part of its share through the ordinary collection book
        $headId = \DB::table('contribution_heads')->where('contribution_book_id', \DB::table('contribution_books')->where('code', $work['book_code'])->value('id'))->value('id');
        $api->postJson("/api/contributions/flats/{$this->flats[0]->id}/collections", [
            'contribution_head_id' => $headId, 'amount' => 10000, 'received_on' => now()->toDateString(), 'method' => 'CASH',
        ])->assertSuccessful();

        $s = $api->getJson("/api/work-budgets/{$work['id']}")->assertOk();
        $s->assertJsonPath('collected', '10000.0000')->assertJsonPath('still_to_collect', '90000.0000')->assertJsonPath('in_hand', '10000.0000');
        $this->assertSame('20000.0000', collect($s->json('rows'))->firstWhere('flat_no', 'A1')['due']);

        // an expense linked to the work is paid from the project fund and counts once approved
        $exp = $api->postJson('/api/expenses', [
            'date' => now()->toDateString(), 'expense_head_id' => \App\Models\ExpenseHead::first()->id, 'payee' => 'Mason', 'amount' => 4000,
            'treasury_medium' => 'CASH', 'work_budget_id' => $work['id'],
        ])->assertCreated();
        $exp->assertJsonPath('fund_type', 'CAPITAL');

        $checker = User::factory()->create(['role' => 'admin']);
        $checker->assignRole('admin');
        $this->actingAs($checker, 'sanctum')->postJson('/api/expenses/'.$exp->json('id').'/approve', ['note' => 'bill seen'])->assertOk();

        $s = $api->getJson("/api/work-budgets/{$work['id']}");
        $s->assertJsonPath('spent', '4000.0000')->assertJsonPath('in_hand', '6000.0000')->assertJsonPath('budget_left', '96000.0000');
    }

    public function test_spending_cannot_pass_the_estimate_or_go_to_a_closed_work(): void
    {
        $work = $this->create(['estimated_cost' => 5000], [['flat_id' => $this->flats[0]->id, 'amount' => 5000]])->json();
        $api = $this->actingAs($this->admin, 'sanctum');
        $head = \App\Models\ExpenseHead::first()->id;
        $body = fn ($amount) => ['date' => now()->toDateString(), 'expense_head_id' => $head, 'payee' => 'X', 'amount' => $amount, 'treasury_medium' => 'CASH', 'work_budget_id' => $work['id']];

        // a work cannot be spent on once it is done
        $api->putJson("/api/work-budgets/{$work['id']}", ['status' => 'DONE'])->assertOk();
        $api->postJson('/api/expenses', $body(100))->assertStatus(422)->assertJsonValidationErrors('work_budget_id');
    }

    public function test_a_collection_schedule_splits_each_share_by_date_and_flags_late_flats(): void
    {
        $past1 = now('Asia/Dhaka')->subDays(40)->toDateString();
        $past2 = now('Asia/Dhaka')->subDays(10)->toDateString();
        $future = now('Asia/Dhaka')->addDays(20)->toDateString();
        $work = $this->create(['installments' => [
            ['due_date' => $past1, 'percent' => 40], ['due_date' => $past2, 'percent' => 30], ['due_date' => $future, 'percent' => 30],
        ]])->assertCreated()->json();
        $api = $this->actingAs($this->admin, 'sanctum');

        // flat A1 (share 30000): 12000 / 9000 / 9000; flat A2 (share 70000): 28000 / 21000 / 21000
        $a1 = collect($work['rows'])->firstWhere('flat_no', 'A1');
        $this->assertSame(['12000.00', '9000.00', '9000.00'], array_column($a1['installments'], 'amount'));
        $this->assertSame('OVERDUE', $a1['installments'][0]['status']);
        $this->assertSame('UPCOMING', $a1['installments'][2]['status']);

        // A1 pays 15000: it settles the first date (12000) and 3000 of the second (9000)
        $headId = \DB::table('contribution_heads')->where('contribution_book_id', \DB::table('contribution_books')->where('code', $work['book_code'])->value('id'))->value('id');
        $api->postJson("/api/contributions/flats/{$this->flats[0]->id}/collections", ['contribution_head_id' => $headId, 'amount' => 15000, 'received_on' => now()->toDateString(), 'method' => 'CASH'])->assertSuccessful();

        $s = $api->getJson("/api/work-budgets/{$work['id']}")->json();
        $a1 = collect($s['rows'])->firstWhere('flat_no', 'A1');
        $this->assertSame(['PAID', 'OVERDUE', 'UPCOMING'], array_column($a1['installments'], 'status'));
        $this->assertSame('6000.00', $a1['installments'][1]['due']);
        $this->assertSame('6000.00', $a1['overdue']);
        // A2 paid nothing: both past dates are late for it too (28000 + 21000)
        $this->assertSame('49000.00', collect($s['rows'])->firstWhere('flat_no', 'A2')['overdue']);
        $this->assertSame('55000.00', $s['overdue_total']);
        $this->assertSame(1, $s['schedule'][0]['overdue_flats']); // the first date is late only for A2 - A1 settled it
        $this->assertSame(2, $s['schedule'][1]['overdue_flats']); // the second is late for both
        $this->assertSame(0, $s['schedule'][2]['overdue_flats']); // the third is not yet due
    }

    public function test_the_schedule_must_add_up_to_100_in_date_order(): void
    {
        $id = $this->create()->json('id');
        $api = $this->actingAs($this->admin, 'sanctum');
        $d = fn ($n) => now()->addDays($n)->toDateString();

        $api->putJson("/api/work-budgets/{$id}/schedule", ['installments' => [['due_date' => $d(5), 'percent' => 50], ['due_date' => $d(9), 'percent' => 40]]])->assertStatus(422)->assertJsonValidationErrors('installments');
        $api->putJson("/api/work-budgets/{$id}/schedule", ['installments' => [['due_date' => $d(9), 'percent' => 50], ['due_date' => $d(5), 'percent' => 50]]])->assertStatus(422);
        $api->putJson("/api/work-budgets/{$id}/schedule", ['installments' => [['due_date' => $d(5), 'percent' => 33.3333], ['due_date' => $d(9), 'percent' => 66.6667]]])->assertOk()->assertJsonCount(2, 'schedule');
        $api->putJson("/api/work-budgets/{$id}/schedule", ['installments' => []])->assertOk()->assertJsonCount(0, 'schedule'); // removed again
    }

    public function test_each_purpose_is_paid_and_owed_separately(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $gasLine = $api->postJson('/api/work-budgets', ['title' => 'Gas line', 'estimated_cost' => 40000, 'split_method' => 'MANUAL', 'shares' => [['flat_id' => $this->flats[0]->id, 'amount' => 20000], ['flat_id' => $this->flats[1]->id, 'amount' => 20000]]])->json();
        $generator = $api->postJson('/api/work-budgets', ['title' => 'Generator', 'estimated_cost' => 30000, 'split_method' => 'MANUAL', 'shares' => [['flat_id' => $this->flats[0]->id, 'amount' => 30000]]])->json();
        $flat = $this->flats[0];

        // the pop-up offers every special collection this flat still owes - and only those
        $offered = $api->getJson("/api/work-budgets/for-flat/{$flat->id}")->assertOk()->json();
        $this->assertSame(['Gas line', 'Generator'], array_column($offered, 'title'));
        $this->assertCount(1, $api->getJson("/api/work-budgets/for-flat/{$this->flats[1]->id}")->json());

        // paying for the gas line reduces the gas line's dues and leaves the generator's untouched
        $paid = $api->postJson("/api/work-budgets/{$gasLine['id']}/collect", ['flat_id' => $flat->id, 'amount' => 5000, 'received_on' => now()->toDateString(), 'method' => 'CASH'])->assertCreated();
        $this->assertStringStartsWith('CC-', $paid->json('receipt_no'));
        $byTitle = collect($api->getJson("/api/work-budgets/for-flat/{$flat->id}")->json())->keyBy('title');
        $this->assertSame('15000.0000', $byTitle['Gas line']['due']);
        $this->assertSame('30000.0000', $byTitle['Generator']['due']);

        // a flat cannot pay more than it owes for that purpose, nor for a work it has no share in
        $api->postJson("/api/work-budgets/{$gasLine['id']}/collect", ['flat_id' => $flat->id, 'amount' => 15001, 'received_on' => now()->toDateString(), 'method' => 'CASH'])->assertStatus(422)->assertJsonValidationErrors('amount');
        $api->postJson("/api/work-budgets/{$generator['id']}/collect", ['flat_id' => $this->flats[2]->id, 'amount' => 100, 'received_on' => now()->toDateString(), 'method' => 'CASH'])->assertStatus(422)->assertJsonValidationErrors('flat_id');
        // a mobile or bank payment needs its reference
        $api->postJson("/api/work-budgets/{$gasLine['id']}/collect", ['flat_id' => $flat->id, 'amount' => 100, 'received_on' => now()->toDateString(), 'method' => 'BKASH'])->assertStatus(422)->assertJsonValidationErrors('reference_no');
    }

    public function test_the_by_purpose_report_has_a_column_for_each_purpose(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $gasLine = $api->postJson('/api/work-budgets', ['title' => 'Gas line', 'estimated_cost' => 40000, 'split_method' => 'MANUAL', 'shares' => [['flat_id' => $this->flats[0]->id, 'amount' => 20000], ['flat_id' => $this->flats[1]->id, 'amount' => 20000]]])->json();
        $api->postJson('/api/work-budgets', ['title' => 'East wall', 'estimated_cost' => 9000, 'split_method' => 'MANUAL', 'shares' => [['flat_id' => $this->flats[1]->id, 'amount' => 9000]]]);
        $api->postJson("/api/work-budgets/{$gasLine['id']}/collect", ['flat_id' => $this->flats[0]->id, 'amount' => 20000, 'received_on' => now()->toDateString(), 'method' => 'CASH'])->assertCreated();

        $r = $api->getJson('/api/reports/simple/purposes')->assertOk()->json();

        $this->assertSame(['Gas line', 'East wall'], array_slice(array_column($r['columns'], 'label'), 4, 2));
        $this->assertCount(1, $r['rows']); // A1 has paid everything; only A2 still owes
        $this->assertSame('A2', $r['rows'][0]['flat']);
        $this->assertSame('29000.00', $r['rows'][0]['total']); // 20000 gas line + 9000 east wall
        $this->assertSame('29000.00', $r['totals']['total']);
    }
}
