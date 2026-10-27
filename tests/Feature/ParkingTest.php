<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\JournalLine;
use App\Models\ParkingAllocation;
use App\Models\ParkingCharge;
use App\Models\ParkingSlot;
use App\Models\Person;
use App\Models\User;
use App\Services\ParkingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CommunityHallSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\ParkingSeeder;
use Database\Seeders\RoleCatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ParkingTest extends TestCase
{
    use RefreshDatabase;

    private ParkingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, CommunityHallSeeder::class, RoleCatalogSeeder::class, ParkingSeeder::class] as $seeder) {
            $this->seed($seeder);
        }
        $this->service = app(ParkingService::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    private function slot(string $code = 'G-01', string $fee = '1500'): ParkingSlot
    {
        return $this->service->createSlot(['code' => $code, 'vehicle_type' => 'CAR', 'monthly_fee' => $fee, 'zone' => 'Ground']);
    }

    private function allocation(ParkingSlot $slot, ?Flat $flat = null, array $over = []): ParkingAllocation
    {
        return $this->service->allocate($slot, array_merge([
            'flat_id' => ($flat ?? Flat::factory()->create())->id, 'vehicle_no' => 'DHAKA-1234', 'from_date' => '2026-09-01',
        ], $over));
    }

    // ---- slots and allocation

    public function test_a_slot_gets_the_default_fee_copied_onto_its_allocation(): void
    {
        $slot = $this->slot('G-01', '1500');

        $allocation = $this->allocation($slot);
        $slot->update(['monthly_fee' => 2500]); // a later price change

        $this->assertSame('1500.0000', $allocation->fresh()->monthly_fee);
    }

    public function test_a_slot_can_only_be_held_by_one_flat_until_released(): void
    {
        $slot = $this->slot();
        $first = $this->allocation($slot);

        try {
            $this->allocation($slot);
            $this->fail('second allocation must be refused');
        } catch (ValidationException) {
        }

        $this->service->release($first, '2026-09-30');
        $second = $this->allocation($slot, null, ['from_date' => '2026-10-01']);
        $this->assertNull($second->to_date);
    }

    public function test_an_inactive_slot_cannot_be_allocated(): void
    {
        $slot = $this->slot();
        $slot->update(['is_active' => false]);

        $this->expectException(ValidationException::class);
        $this->allocation($slot);
    }

    public function test_release_cannot_end_before_the_start_or_twice(): void
    {
        $allocation = $this->allocation($this->slot());

        try {
            $this->service->release($allocation, '2026-08-01');
            $this->fail('end before start must be refused');
        } catch (ValidationException) {
        }

        $this->service->release($allocation, '2026-09-30');
        $this->expectException(ValidationException::class);
        $this->service->release($allocation->fresh(), '2026-10-05');
    }

    // ---- monthly charges

    public function test_generating_charges_covers_active_allocations_once_per_month(): void
    {
        $this->allocation($this->slot('G-01', '1500'));
        $this->allocation($this->slot('G-02', '1000'));

        $first = $this->service->generateCharges(2026, 10);
        $again = $this->service->generateCharges(2026, 10);

        $this->assertSame(['created' => 2, 'skipped' => 0], $first);
        $this->assertSame(['created' => 0, 'skipped' => 2], $again);
        $this->assertSame(2, ParkingCharge::count());
        $this->assertSame('2026-10-31', ParkingCharge::first()->due_date->toDateString());
    }

    public function test_free_allocations_produce_no_charge(): void
    {
        $this->allocation($this->slot('G-01', '0'));

        $this->assertSame(['created' => 0, 'skipped' => 0], $this->service->generateCharges(2026, 10));
    }

    public function test_only_months_the_allocation_covers_are_charged(): void
    {
        $a = $this->allocation($this->slot(), null, ['from_date' => '2026-09-15']);
        $this->service->release($a, '2026-10-10');

        $this->assertSame(0, $this->service->generateCharges(2026, 8)['created']); // before it started
        $this->assertSame(1, $this->service->generateCharges(2026, 9)['created']); // started mid-month: charged
        $this->assertSame(1, $this->service->generateCharges(2026, 10)['created']); // ended mid-month: charged
        $this->assertSame(0, $this->service->generateCharges(2026, 11)['created']); // after it ended
    }

    // ---- collection

    public function test_payment_posts_cash_ledger_and_a_balanced_journal_to_parking_income(): void
    {
        $admin = $this->user('admin');
        $this->allocation($this->slot('G-01', '1500'));
        $this->service->generateCharges(2026, 10);
        $charge = ParkingCharge::first();

        $payment = $this->service->recordPayment($charge, ['amount' => 1000, 'method' => 'CASH', 'paid_on' => '2026-10-05'], $admin->id);

        $this->assertSame('PK-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT), $payment->receipt_no);
        $this->assertDatabaseHas('cash_ledger', ['in_amount' => 1000]);
        $lines = JournalLine::where('journal_entry_id', $payment->journal_entry_id)->get();
        $this->assertEquals($lines->sum('debit'), $lines->sum('credit'));
        $income = ChartOfAccount::where('code', '4700')->first();
        $this->assertSame(1000.0, (float) $lines->firstWhere('account_id', $income->id)->credit);
    }

    public function test_what_is_due_is_derived_and_overpaying_is_refused(): void
    {
        $admin = $this->user('admin');
        $this->allocation($this->slot('G-01', '1500'));
        $this->service->generateCharges(2026, 10);
        $charge = ParkingCharge::first();

        $this->service->recordPayment($charge, ['amount' => 500, 'method' => 'CASH', 'paid_on' => '2026-10-05'], $admin->id);
        $this->assertSame('1000.0000', $charge->due());

        $this->service->recordPayment($charge, ['amount' => 1000, 'method' => 'CASH', 'paid_on' => '2026-10-06'], $admin->id);
        $this->assertSame('0.0000', $charge->due());

        $this->expectException(ValidationException::class);
        $this->service->recordPayment($charge, ['amount' => 1, 'method' => 'CASH', 'paid_on' => '2026-10-07'], $admin->id);
    }

    public function test_summary_adds_up_charged_collected_and_due(): void
    {
        $admin = $this->user('admin');
        $this->allocation($this->slot('G-01', '1500'));
        $this->allocation($this->slot('G-02', '1000'));
        $this->service->generateCharges(2026, 10);
        $this->service->recordPayment(ParkingCharge::orderBy('id')->first(), ['amount' => 1500, 'method' => 'CASH', 'paid_on' => '2026-10-05'], $admin->id);

        $s = $this->service->summary(2026, 10);

        $this->assertSame('2500.0000', $s['charged']);
        $this->assertSame('1500.0000', $s['collected']);
        $this->assertSame('1000.0000', $s['due']);
        $this->assertSame(2, $s['slots_taken']);
    }

    // ---- HTTP and permissions

    public function test_admin_runs_the_whole_flow_over_http(): void
    {
        $this->actingAs($this->user('admin'), 'sanctum');
        $flat = Flat::factory()->create();

        $slotId = $this->postJson('/api/parking/slots', ['code' => 'B-07', 'vehicle_type' => 'CAR', 'monthly_fee' => 2000])->assertCreated()->json('id');
        $this->postJson('/api/parking/slots', ['code' => 'B-07', 'vehicle_type' => 'CAR', 'monthly_fee' => 1])->assertStatus(422)->assertJsonValidationErrors('code');

        $this->postJson("/api/parking/slots/{$slotId}/allocate", ['flat_id' => $flat->id, 'vehicle_no' => 'D-1', 'from_date' => '2026-10-01'])
            ->assertCreated()->assertJsonPath('holder.flat_id', $flat->id);

        $this->postJson('/api/parking/charges/generate', ['year' => 2026, 'month' => 10])->assertOk()->assertJsonPath('created', 1);
        $charge = $this->getJson('/api/parking/charges?year=2026&month=10')->assertOk()->json('0');
        $this->assertSame('UNPAID', $charge['status']);

        $this->postJson("/api/parking/charges/{$charge['id']}/payments", ['amount' => 2000, 'method' => 'CASH', 'paid_on' => '2026-10-05'])
            ->assertOk()->assertJsonPath('status', 'PAID')->assertJsonPath('due', '0.0000');
        $this->getJson('/api/parking/summary?year=2026&month=10')->assertOk()->assertJsonPath('collected', '2000.0000');
    }

    public function test_committee_roles_get_the_right_slice_of_parking(): void
    {
        $slot = $this->slot();
        $this->allocation($slot);
        $this->service->generateCharges(2026, 10);
        $charge = ParkingCharge::first();

        $member = $this->user('committee_member');
        $treasurer = $this->user('committee_treasurer');
        $president = $this->user('committee_president');

        // member: look only
        $this->actingAs($member, 'sanctum')->getJson('/api/parking/slots')->assertOk();
        $this->actingAs($member, 'sanctum')->postJson('/api/parking/charges/generate', ['year' => 2026, 'month' => 11])->assertForbidden();
        $this->actingAs($member, 'sanctum')->postJson("/api/parking/charges/{$charge->id}/payments", ['amount' => 1, 'method' => 'CASH', 'paid_on' => '2026-10-05'])->assertForbidden();
        // treasurer: collects, but does not manage slots
        $this->actingAs($treasurer, 'sanctum')->postJson("/api/parking/charges/{$charge->id}/payments", ['amount' => 100, 'method' => 'CASH', 'paid_on' => '2026-10-05'])->assertOk();
        $this->actingAs($treasurer, 'sanctum')->postJson('/api/parking/slots', ['code' => 'X-1', 'vehicle_type' => 'CAR', 'monthly_fee' => 1])->assertForbidden();
        // president: manages, but does not collect
        $this->actingAs($president, 'sanctum')->postJson('/api/parking/slots', ['code' => 'X-1', 'vehicle_type' => 'CAR', 'monthly_fee' => 1])->assertCreated();
        $this->actingAs($president, 'sanctum')->postJson("/api/parking/charges/{$charge->id}/payments", ['amount' => 1, 'method' => 'CASH', 'paid_on' => '2026-10-05'])->assertForbidden();
    }

    public function test_a_resident_sees_only_their_own_flats_parking(): void
    {
        $person = Person::factory()->create();
        $resident = User::factory()->create(['person_id' => $person->id, 'role' => 'flat_owner']);
        $resident->assignRole('flat_owner');
        $mine = Flat::factory()->create();
        FlatParty::create(['flat_id' => $mine->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2026-01-01']);

        $this->allocation($this->slot('G-01', '1500'), $mine);
        $this->allocation($this->slot('G-02', '1000'));
        $this->service->generateCharges(2026, 10);

        $res = $this->actingAs($resident, 'sanctum')->getJson('/api/me/parking')->assertOk();

        $this->assertSame(['G-01'], collect($res->json('slots'))->pluck('slot_code')->all());
        $this->assertSame(['G-01'], collect($res->json('charges'))->pluck('slot_code')->all());
        $this->actingAs($resident, 'sanctum')->getJson('/api/parking/slots')->assertForbidden();
    }
}
