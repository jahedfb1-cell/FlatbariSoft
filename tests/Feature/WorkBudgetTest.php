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
}
