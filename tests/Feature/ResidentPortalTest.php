<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\BuildingContact;
use App\Models\Complaint;
use App\Models\ContributionHead;
use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\GasConsumer;
use App\Models\GasMeterReading;
use App\Models\Meeting;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Resolution;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ContributionService;
use App\Services\PaymentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CommunityHallSeeder;
use Database\Seeders\ContributionSeeder;
use Database\Seeders\MoneyCorePermissionSeeder;
use Database\Seeders\RoleCatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** What a flat owner or tenant sees at home and in their bills, the building's numbers, the committee's decisions, and the notices about their payments. */
class ResidentPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([ChartOfAccountsSeeder::class, RoleSeeder::class, MoneyCorePermissionSeeder::class, CommunityHallSeeder::class, RoleCatalogSeeder::class, ContributionSeeder::class] as $seeder) {
            $this->seed($seeder);
        }
        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->admin->assignRole('super_admin');
    }

    private function resident(Flat $flat, string $role = 'OWNER', string $nameEn = 'Nazrul Islam'): User
    {
        $person = Person::factory()->create(['name_en' => $nameEn]);
        FlatParty::create(['flat_id' => $flat->id, 'person_id' => $person->id, 'role' => $role, 'from_date' => '2026-01-01']);

        $user = User::factory()->create(['person_id' => $person->id, 'role' => $role === 'OWNER' ? 'flat_owner' : 'tenant']);
        $user->assignRole($role === 'OWNER' ? 'flat_owner' : 'tenant');

        return $user;
    }

    /** A September bill for the flat: service 3,500 + gas 103 units (219 -> 322) x 325 + previous due 500. */
    private function bill(Flat $flat, string $dueDate = '2026-10-10'): Bill
    {
        $period = BillingPeriod::firstOrCreate(['year' => 2026, 'month' => 9], ['status' => 'GENERATED']);
        $consumer = GasConsumer::firstOrCreate(['flat_id' => $flat->id], ['name' => 'Flat '.$flat->flat_no, 'is_internal' => false, 'active' => true]);
        GasMeterReading::create(['gas_consumer_id' => $consumer->id, 'billing_period_id' => $period->id, 'previous_reading' => 219, 'current_reading' => 322, 'units_used' => 103, 'reading_date' => '2026-09-30', 'entered_by' => $this->admin->id]);

        return Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => $flat->id, 'service_charge' => 3500, 'gas_amount' => 33475, 'previous_due' => 500,
            'total_payable' => 37475, 'paid_amount' => 0, 'due_date' => $dueDate, 'status' => 'UNPAID',
        ]);
    }

    // ---- the home card

    public function test_an_owners_home_summary_shows_the_flat_the_bill_the_tenant_and_the_construction_balance(): void
    {
        $flat = Flat::factory()->create(['flat_no' => '10A', 'size_sqft' => 1200, 'gas_meter_no' => 'GM-77', 'occupancy_status' => 'TENANTED']);
        $owner = $this->resident($flat);
        Tenant::factory()->create(['flat_id' => $flat->id, 'person_id' => Person::factory()->create(['name_en' => 'Karim Tenant'])->id]);
        $this->bill($flat, '2020-01-01'); // long past: overdue
        app(ContributionService::class)->record($flat, ['contribution_head_id' => ContributionHead::where('code', 'SUBSCRIPTION')->value('id'), 'amount' => 100000, 'received_on' => '2026-09-01', 'method' => 'CASH'], $this->admin->id);

        $r = $this->actingAs($owner, 'sanctum')->getJson('/api/me/summary')->assertOk();

        $f = $r->json('flats.0');
        $this->assertSame('10A', $f['flat_no']);
        $this->assertSame('OWNER', $f['role']);
        $this->assertSame('GM-77', $f['gas_meter_no']);
        $this->assertSame('Karim Tenant', $f['tenant']['name']);
        $this->assertFalse($f['tenant']['kyc_complete']);
        $this->assertSame('37475.0000', $f['latest_bill']['total_payable']);
        $this->assertSame('37475.0000', $f['latest_bill']['due']);
        $this->assertTrue($f['latest_bill']['overdue']);
        $this->assertTrue($r->json('totals.overdue'));
        $this->assertSame('37475.0000', $r->json('totals.bill_due'));
        $this->assertNotNull($r->json('totals.construction_due')); // 2,550,000 target less 100,000 paid
        $this->assertSame('2450000.0000', $r->json('totals.construction_due'));
    }

    public function test_a_tenant_sees_the_flat_and_its_bill_but_neither_the_construction_balance_nor_the_tenant_card(): void
    {
        $flat = Flat::factory()->create(['flat_no' => '9C']);
        $tenant = $this->resident($flat, 'TENANT', 'Karim Tenant');
        $this->bill($flat);

        $r = $this->actingAs($tenant, 'sanctum')->getJson('/api/me/summary')->assertOk();

        $this->assertSame('TENANT', $r->json('flats.0.role'));
        $this->assertNull($r->json('flats.0.tenant'));
        $this->assertNull($r->json('totals.construction_due'));
        $this->assertSame('37475.0000', $r->json('totals.bill_due'));
    }

    public function test_the_summary_counts_open_complaints_and_never_shows_another_flat(): void
    {
        $mine = Flat::factory()->create(['flat_no' => '10A']);
        $other = Flat::factory()->create(['flat_no' => '10B']);
        $owner = $this->resident($mine);
        $this->resident($other, 'OWNER', 'Someone Else');
        $this->bill($other);
        Complaint::create(['flat_id' => $mine->id, 'category' => 'LIFT', 'description' => 'x', 'priority' => 'MEDIUM', 'status' => 'OPEN']);
        Complaint::create(['flat_id' => $other->id, 'category' => 'LIFT', 'description' => 'y', 'priority' => 'MEDIUM', 'status' => 'OPEN']);

        $r = $this->actingAs($owner, 'sanctum')->getJson('/api/me/summary')->assertOk();

        $this->assertCount(1, $r->json('flats'));
        $this->assertNull($r->json('flats.0.latest_bill')); // 10B's bill is not mine
        $this->assertSame(1, $r->json('counts.open_complaints'));
        $this->assertSame('0', $r->json('totals.bill_due'));
    }

    // ---- bills

    public function test_the_bill_comes_with_its_breakdown_and_the_gas_meter_figures(): void
    {
        $flat = Flat::factory()->create(['flat_no' => '10A']);
        $owner = $this->resident($flat);
        $this->bill($flat);

        $b = $this->actingAs($owner, 'sanctum')->getJson('/api/me/bills/details')->assertOk()->json('0');

        $this->assertSame('Sep 2026', $b['month_label']);
        $this->assertSame('500.0000', $b['previous_due']);
        $this->assertSame('3500.0000', $b['service_charge']);
        $this->assertSame('219.0000', $b['gas_previous']);
        $this->assertSame('322.0000', $b['gas_current']);
        $this->assertSame('103.0000', $b['gas_units']);
        $this->assertSame('325.00', $b['gas_rate']);
        $this->assertSame('37475.0000', $b['total_payable']);
    }

    public function test_the_residents_own_bill_pdf_is_named_flat_owner_bill_and_another_flats_bill_is_not_found(): void
    {
        $flat = Flat::factory()->create(['flat_no' => '10A']);
        $owner = $this->resident($flat);
        $mine = $this->bill($flat);
        $other = Flat::factory()->create(['flat_no' => '10B']);
        $theirs = $this->bill($other);

        $res = $this->actingAs($owner, 'sanctum')->get("/api/me/bills/{$mine->id}/pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString('10A-Nazrul Islam-Bill Sep 2026.pdf', rawurldecode((string) $res->headers->get('Content-Disposition')));

        $this->actingAs($owner, 'sanctum')->getJson("/api/me/bills/{$theirs->id}/pdf")->assertNotFound();
    }

    // ---- decisions and numbers

    public function test_only_decisions_of_meetings_that_were_held_and_not_cancelled_are_shown(): void
    {
        $held = Meeting::create(['title' => 'AGM', 'meeting_date' => '2026-09-01', 'status' => 'HELD']);
        $called = Meeting::create(['title' => 'Called off', 'meeting_date' => '2026-09-05', 'status' => 'CANCELLED']);
        Resolution::create(['meeting_id' => $held->id, 'resolution_no' => 'R-1', 'description' => 'Raise service charge', 'decision' => 'Approved', 'status' => 'DONE']);
        Resolution::create(['meeting_id' => $held->id, 'resolution_no' => 'R-2', 'description' => 'Dropped idea', 'decision' => 'No', 'status' => 'CANCELLED']);
        Resolution::create(['meeting_id' => $called->id, 'resolution_no' => 'R-3', 'description' => 'Never decided', 'decision' => '-', 'status' => 'PENDING']);
        $owner = $this->resident(Flat::factory()->create());

        $rows = $this->actingAs($owner, 'sanctum')->getJson('/api/me/decisions')->assertOk()->json();

        $this->assertSame(['R-1'], array_column($rows, 'resolution_no'));
    }

    public function test_the_office_keeps_the_building_numbers_and_residents_read_only_the_active_ones(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $api->postJson('/api/building-contacts', ['name' => 'Rahim', 'role' => 'Guard', 'phone' => '01711-000000'])->assertCreated();
        $id = $api->postJson('/api/building-contacts', ['name' => 'Old lift man', 'role' => 'Lift', 'phone' => '01800000000'])->assertCreated()->json('id');
        $api->putJson("/api/building-contacts/{$id}", ['name' => 'Old lift man', 'phone' => '01800000000', 'is_active' => false])->assertOk();
        $api->postJson('/api/building-contacts', ['name' => 'Bad', 'phone' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('phone');

        $owner = $this->resident(Flat::factory()->create());
        $seen = $this->actingAs($owner, 'sanctum')->getJson('/api/me/contacts')->assertOk()->json();
        $this->assertSame(['Rahim'], array_column($seen, 'name'));

        // a resident cannot manage them
        $this->actingAs($owner, 'sanctum')->postJson('/api/building-contacts', ['name' => 'X', 'phone' => '01700000000'])->assertForbidden();
        $this->assertSame(2, BuildingContact::count());
    }

    // ---- notices about the payment

    private function reportedPayment(Flat $flat): Payment
    {
        return app(PaymentService::class)->create(['flat_id' => $flat->id, 'amount' => 1000, 'payment_date' => '2026-09-12', 'method' => 'CASH'], $this->admin->id);
    }

    public function test_approving_a_payment_tells_the_flats_owner_and_tenant(): void
    {
        $flat = Flat::factory()->create(['flat_no' => '10A']);
        $owner = $this->resident($flat);
        $tenant = $this->resident($flat, 'TENANT', 'Karim Tenant');
        $stranger = $this->resident(Flat::factory()->create(['flat_no' => '10B']), 'OWNER', 'Someone Else');

        app(PaymentService::class)->approve($this->reportedPayment($flat), $this->admin->id);

        $this->assertSame('PAYMENT_APPROVED', $owner->fresh()->notifications->first()->data['kind']);
        $this->assertStringContainsString('MR-', $owner->fresh()->notifications->first()->data['body']);
        $this->assertCount(1, $tenant->fresh()->notifications);
        $this->assertCount(0, $stranger->fresh()->notifications);
    }

    public function test_rejecting_a_pending_payment_needs_a_reason_removes_it_logs_it_and_tells_the_flat(): void
    {
        $flat = Flat::factory()->create(['flat_no' => '10A']);
        $owner = $this->resident($flat);
        $payment = $this->reportedPayment($flat);
        $api = $this->actingAs($this->admin, 'sanctum');

        $api->postJson("/api/payments/{$payment->id}/reject", [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $api->postJson("/api/payments/{$payment->id}/reject", ['reason' => 'Transaction number not found'])->assertOk();

        $this->assertNull(Payment::find($payment->id));
        $log = AuditLog::where('event', 'rejected')->where('auditable_type', 'Payment')->firstOrFail();
        $this->assertSame('Transaction number not found', $log->new_values['reason']);
        $note = $owner->fresh()->notifications->first();
        $this->assertSame('PAYMENT_REJECTED', $note->data['kind']);
        $this->assertStringContainsString('Transaction number not found', $note->data['body']);
    }

    public function test_an_approved_payment_cannot_be_rejected_and_only_approvers_may_reject(): void
    {
        $flat = Flat::factory()->create();
        $approved = app(PaymentService::class)->approve($this->reportedPayment($flat), $this->admin->id);
        $pending = $this->reportedPayment($flat);

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/payments/{$approved->id}/reject", ['reason' => 'too late'])->assertStatus(422);
        $this->assertNotNull(Payment::find($approved->id));

        $manager = User::factory()->create(['role' => 'manager']);
        $manager->assignRole('manager'); // may enter payments, may not approve them
        $this->actingAs($manager, 'sanctum')->postJson("/api/payments/{$pending->id}/reject", ['reason' => 'because'])->assertForbidden();
    }
}
