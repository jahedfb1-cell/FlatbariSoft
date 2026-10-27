<?php

namespace Tests\Feature;

use App\Models\CommunityHall;
use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\HallBooking;
use App\Models\Person;
use App\Models\User;
use App\Services\HallBookingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CommunityHallSeeder;
use Database\Seeders\RoleCatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HallBookingTest extends TestCase
{
    use RefreshDatabase;

    private HallBookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(CommunityHallSeeder::class);
        $this->seed(RoleCatalogSeeder::class);
        $this->service = app(HallBookingService::class);

        // seeded rates are 0 on purpose - the office sets them
        $hall = CommunityHall::first();
        $hall->update(['security_deposit' => 5000]);
        $hall->rates()->where('slot', 'DAY')->update(['resident_rate' => 10000, 'outsider_rate' => 20000]);
        $hall->rates()->where('slot', 'NIGHT')->update(['resident_rate' => 12000, 'outsider_rate' => 24000]);
        $hall->rates()->where('slot', 'FULL_DAY')->update(['resident_rate' => 20000, 'outsider_rate' => 40000]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    private function date(int $daysAhead = 20): string
    {
        return now()->addDays($daysAhead)->toDateString();
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'customer_name' => 'রহিম উদ্দিন', 'customer_mobile' => '01711111111',
            'event_type' => 'WEDDING', 'guest_count' => 150,
            'booking_date' => $this->date(), 'slot' => 'DAY',
        ], $over);
    }

    private function confirmed(array $over = []): HallBooking
    {
        $admin = $this->user('admin');

        return $this->service->create($this->payload($over), $admin->id, autoConfirm: true);
    }

    public function test_seeded_rates_are_unset_so_nothing_can_be_booked_until_rates_are_set(): void
    {
        CommunityHall::first()->rates()->update(['resident_rate' => null, 'outsider_rate' => null]);

        $this->expectException(ValidationException::class);
        $this->service->quote('DAY', true);
    }

    public function test_a_zero_resident_rate_means_free_not_missing(): void
    {
        CommunityHall::first()->rates()->where('slot', 'DAY')->update(['resident_rate' => 0]);
        $admin = $this->user('admin');
        $flat = Flat::factory()->create();

        $booking = $this->service->create($this->payload(['flat_id' => $flat->id]), $admin->id, autoConfirm: true);

        $this->assertSame('0.0000', $booking->total_amount);
        $this->assertSame('5000.0000', $booking->security_deposit); // a deposit can still apply
        // nothing to collect, so a rent payment is refused
        $this->expectException(ValidationException::class);
        $this->service->recordPayment($booking, ['type' => 'RENT', 'amount' => 1, 'method' => 'CASH', 'paid_on' => now()->toDateString()], $admin->id);
    }

    public function test_a_partial_resident_rate_is_charged_while_outsiders_pay_full(): void
    {
        CommunityHall::first()->rates()->where('slot', 'DAY')->update(['resident_rate' => 2500]);

        $this->assertSame('2500.0000', $this->service->quote('DAY', true)['rent']);
        $this->assertSame('20000.0000', $this->service->quote('DAY', false)['rent']);
    }

    public function test_quote_uses_resident_or_outsider_rate_and_the_hall_deposit(): void
    {
        $resident = $this->service->quote('DAY', true);
        $outsider = $this->service->quote('DAY', false);

        $this->assertSame('10000.0000', $resident['rent']);
        $this->assertSame('20000.0000', $outsider['rent']);
        $this->assertSame('5000.0000', $resident['security_deposit']);
    }

    public function test_booking_gets_a_number_and_snapshots_price_and_hours(): void
    {
        $booking = $this->confirmed();

        $this->assertSame('HB-'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT), $booking->booking_no);
        $this->assertSame('CONFIRMED', $booking->status);
        $this->assertSame('20000.0000', $booking->total_amount); // outsider, no flat
        $this->assertSame('09:00', substr($booking->start_time, 0, 5)); // MySQL says 09:00:00, sqlite 09:00

        // a later rate change must not rewrite an existing booking
        CommunityHall::first()->rates()->where('slot', 'DAY')->update(['outsider_rate' => 99999]);
        $this->assertSame('20000.0000', $booking->fresh()->total_amount);
    }

    public function test_extra_charge_and_discount_adjust_the_total(): void
    {
        $booking = $this->confirmed(['extra_charge' => 1500, 'discount' => 500]);

        $this->assertSame('21000.0000', $booking->total_amount);
    }

    public function test_same_slot_on_the_same_date_cannot_be_double_booked(): void
    {
        $this->confirmed();

        $this->expectException(ValidationException::class);
        $this->confirmed();
    }

    public function test_day_and_night_can_share_a_date_but_full_day_blocks_both(): void
    {
        $this->confirmed(['slot' => 'DAY']);
        $this->confirmed(['slot' => 'NIGHT']); // fine, different slot

        $this->expectException(ValidationException::class);
        $this->confirmed(['slot' => 'FULL_DAY']);
    }

    public function test_full_day_blocks_a_later_day_request(): void
    {
        $this->confirmed(['slot' => 'FULL_DAY']);

        $this->expectException(ValidationException::class);
        $this->confirmed(['slot' => 'DAY']);
    }

    public function test_cancelled_and_rejected_bookings_free_the_slot(): void
    {
        $admin = $this->user('admin');
        $first = $this->confirmed();
        $this->service->cancel($first, 'কারণ', $admin->id);

        $second = $this->service->create($this->payload(), $admin->id); // PENDING
        $this->service->reject($second, 'কারণ', $admin->id);

        $third = $this->confirmed();
        $this->assertSame('CONFIRMED', $third->status);
    }

    public function test_a_pending_request_holds_the_slot(): void
    {
        $admin = $this->user('admin');
        $this->service->create($this->payload(), $admin->id);

        $this->expectException(ValidationException::class);
        $this->service->create($this->payload(['customer_name' => 'অন্য কেউ']), $admin->id);
    }

    public function test_past_dates_are_refused(): void
    {
        $admin = $this->user('admin');

        $this->expectException(ValidationException::class);
        $this->service->create($this->payload(['booking_date' => now()->subDay()->toDateString()]), $admin->id);
    }

    public function test_approving_moves_pending_to_confirmed_once(): void
    {
        $admin = $this->user('admin');
        $pending = $this->service->create($this->payload(), $admin->id);
        $this->assertSame('PENDING', $pending->status);

        $approved = $this->service->approve($pending, $admin->id);
        $this->assertSame('CONFIRMED', $approved->status);
        $this->assertSame($admin->id, $approved->approved_by);

        $this->expectException(ValidationException::class);
        $this->service->approve($approved, $admin->id);
    }

    public function test_rent_payment_posts_cash_ledger_and_a_balanced_journal_to_hall_income(): void
    {
        $admin = $this->user('admin');
        $booking = $this->confirmed();

        $payment = $this->service->recordPayment($booking, [
            'type' => 'RENT', 'amount' => 8000, 'method' => 'CASH', 'paid_on' => now()->toDateString(),
        ], $admin->id);

        $this->assertSame('HR-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT), $payment->receipt_no);
        $this->assertDatabaseHas('cash_ledger', ['in_amount' => 8000]);
        $this->assertNotNull($payment->journal_entry_id);

        $lines = \App\Models\JournalLine::where('journal_entry_id', $payment->journal_entry_id)->get();
        $this->assertSame(2, $lines->count());
        $this->assertEquals($lines->sum('debit'), $lines->sum('credit'));
        $income = \App\Models\ChartOfAccount::where('code', '4600')->first();
        $this->assertSame(8000.0, (float) $lines->firstWhere('account_id', $income->id)->credit);
    }

    public function test_deposit_is_a_liability_not_income(): void
    {
        $admin = $this->user('admin');
        $booking = $this->confirmed();

        $payment = $this->service->recordPayment($booking, [
            'type' => 'DEPOSIT', 'amount' => 5000, 'method' => 'CASH', 'paid_on' => now()->toDateString(),
        ], $admin->id);

        $liability = \App\Models\ChartOfAccount::where('code', '2300')->first();
        $income = \App\Models\ChartOfAccount::where('code', '4600')->first();
        $lines = \App\Models\JournalLine::where('journal_entry_id', $payment->journal_entry_id)->get();

        $this->assertSame(5000.0, (float) $lines->firstWhere('account_id', $liability->id)->credit);
        $this->assertNull($lines->firstWhere('account_id', $income->id));
    }

    public function test_bank_payment_goes_to_the_bank_ledger(): void
    {
        \App\Models\BankAccount::create([
            'bank_name' => 'Test Bank', 'account_title' => 'GV', 'account_no' => '123',
            'opening_balance' => 0, 'current_balance' => 0,
        ]);
        $admin = $this->user('admin');
        $booking = $this->confirmed();

        $this->service->recordPayment($booking, [
            'type' => 'RENT', 'amount' => 3000, 'method' => 'BANK', 'paid_on' => now()->toDateString(), 'reference_no' => 'TXN1',
        ], $admin->id);

        $this->assertDatabaseHas('bank_transactions', ['debit' => 3000]);
        $this->assertSame('3000.0000', \App\Models\BankAccount::first()->current_balance);
    }

    public function test_cannot_pay_more_than_the_rent_due_or_before_confirmation(): void
    {
        $admin = $this->user('admin');
        $pending = $this->service->create($this->payload(), $admin->id);

        try {
            $this->service->recordPayment($pending, ['type' => 'RENT', 'amount' => 100, 'method' => 'CASH', 'paid_on' => now()->toDateString()], $admin->id);
            $this->fail('payment on a pending booking must be refused');
        } catch (ValidationException) {
        }

        $confirmed = $this->service->approve($pending, $admin->id);
        $this->expectException(ValidationException::class);
        $this->service->recordPayment($confirmed, ['type' => 'RENT', 'amount' => 20000.01, 'method' => 'CASH', 'paid_on' => now()->toDateString()], $admin->id);
    }

    public function test_rent_due_goes_down_as_payments_come_in(): void
    {
        $admin = $this->user('admin');
        $booking = $this->confirmed();
        $this->service->recordPayment($booking, ['type' => 'RENT', 'amount' => 5000, 'method' => 'CASH', 'paid_on' => now()->toDateString()], $admin->id);
        $this->service->recordPayment($booking, ['type' => 'RENT', 'amount' => 15000, 'method' => 'CASH', 'paid_on' => now()->toDateString()], $admin->id);

        $this->assertSame('20000.0000', $booking->netPaid('RENT'));

        $this->expectException(ValidationException::class);
        $this->service->recordPayment($booking, ['type' => 'RENT', 'amount' => 1, 'method' => 'CASH', 'paid_on' => now()->toDateString()], $admin->id);
    }

    public function test_deposit_is_refunded_only_after_the_event_is_completed(): void
    {
        $admin = $this->user('admin');
        $booking = $this->confirmed(['booking_date' => now('Asia/Dhaka')->toDateString()]); // the hall's "today" is the Dhaka day
        $this->service->recordPayment($booking, ['type' => 'DEPOSIT', 'amount' => 5000, 'method' => 'CASH', 'paid_on' => now()->toDateString()], $admin->id);

        try {
            $this->service->recordPayment($booking, ['type' => 'DEPOSIT_REFUND', 'amount' => 5000, 'method' => 'CASH', 'paid_on' => now()->toDateString()], $admin->id);
            $this->fail('refund before completion must be refused');
        } catch (ValidationException) {
        }

        $this->service->complete($booking);
        $this->service->recordPayment($booking->fresh(), ['type' => 'DEPOSIT_REFUND', 'amount' => 4000, 'method' => 'CASH', 'paid_on' => now()->toDateString()], $admin->id);

        $this->assertSame('1000.0000', $booking->netPaid('DEPOSIT'));
        $this->assertDatabaseHas('cash_ledger', ['out_amount' => 4000]);
    }

    public function test_cannot_complete_before_the_event_date(): void
    {
        $booking = $this->confirmed();

        $this->expectException(ValidationException::class);
        $this->service->complete($booking);
    }

    public function test_early_cancellation_refunds_all_rent_and_the_deposit(): void
    {
        $admin = $this->user('admin');
        $booking = $this->confirmed(['booking_date' => $this->date(30)]); // policy: >= 7 days ahead = full refund
        $this->service->recordPayment($booking, ['type' => 'RENT', 'amount' => 10000, 'method' => 'CASH', 'paid_on' => now()->toDateString()], $admin->id);
        $this->service->recordPayment($booking, ['type' => 'DEPOSIT', 'amount' => 5000, 'method' => 'CASH', 'paid_on' => now()->toDateString()], $admin->id);

        $cancelled = $this->service->cancel($booking, 'পরিকল্পনা বদল', $admin->id);

        $this->assertSame('CANCELLED', $cancelled->status);
        $this->assertSame('0.0000', $cancelled->netPaid('RENT'));
        $this->assertSame('0.0000', $cancelled->netPaid('DEPOSIT'));
        $this->assertDatabaseHas('cash_ledger', ['out_amount' => 10000]);
        $this->assertDatabaseHas('cash_ledger', ['out_amount' => 5000]);
    }

    public function test_late_cancellation_refunds_only_the_policy_share_of_rent(): void
    {
        $admin = $this->user('admin');
        $booking = $this->confirmed(['booking_date' => $this->date(3)]); // inside the 7-day window -> 50%
        $this->service->recordPayment($booking, ['type' => 'RENT', 'amount' => 10000, 'method' => 'CASH', 'paid_on' => now()->toDateString()], $admin->id);

        $cancelled = $this->service->cancel($booking, 'দেরিতে বাতিল', $admin->id);

        $this->assertSame('5000.0000', $cancelled->netPaid('RENT')); // the hall keeps half
    }

    public function test_office_can_override_the_refund_but_never_above_what_was_paid(): void
    {
        $admin = $this->user('admin');
        $booking = $this->confirmed();
        $this->service->recordPayment($booking, ['type' => 'RENT', 'amount' => 10000, 'method' => 'CASH', 'paid_on' => now()->toDateString()], $admin->id);

        try {
            $this->service->cancel($booking, 'x', $admin->id, '10000.01');
            $this->fail('refund above the amount paid must be refused');
        } catch (ValidationException) {
        }

        $this->assertSame('CONFIRMED', $booking->fresh()->status); // the failed cancel rolled back completely
        $cancelled = $this->service->cancel($booking, 'x', $admin->id, '2500');
        $this->assertSame('7500.0000', $cancelled->netPaid('RENT'));
    }

    // ---- HTTP + permissions

    public function test_manager_can_request_a_booking_but_it_stays_pending_and_cannot_be_approved(): void
    {
        $manager = $this->user('manager');
        $this->actingAs($manager, 'sanctum');

        $res = $this->postJson('/api/hall/bookings', $this->payload(['discount' => 9000]))->assertCreated();
        $this->assertSame('PENDING', $res->json('status'));
        $this->assertSame('20000.0000', $res->json('total_amount')); // manager's discount was ignored

        $this->postJson('/api/hall/bookings/'.$res->json('id').'/approve')->assertForbidden();
    }

    public function test_admin_booking_is_confirmed_immediately_and_can_take_payment_over_http(): void
    {
        $this->actingAs($this->user('admin'), 'sanctum');

        $res = $this->postJson('/api/hall/bookings', $this->payload())->assertCreated();
        $this->assertSame('CONFIRMED', $res->json('status'));

        $this->postJson('/api/hall/bookings/'.$res->json('id').'/payments', [
            'type' => 'RENT', 'amount' => 6000, 'method' => 'CASH', 'paid_on' => now()->toDateString(),
        ])->assertOk()->assertJsonPath('rent_paid', '6000.0000')->assertJsonPath('rent_due', '14000.0000');
    }

    public function test_double_booking_over_http_is_a_422(): void
    {
        $this->actingAs($this->user('admin'), 'sanctum');
        $this->postJson('/api/hall/bookings', $this->payload())->assertCreated();

        $this->postJson('/api/hall/bookings', $this->payload())->assertStatus(422)->assertJsonValidationErrors('slot');
    }

    public function test_availability_lists_taken_slots_without_customer_details(): void
    {
        $this->actingAs($this->user('admin'), 'sanctum');
        $date = $this->date();
        $this->postJson('/api/hall/bookings', $this->payload(['booking_date' => $date]))->assertCreated();

        $res = $this->getJson('/api/hall/availability?month='.substr($date, 0, 7))->assertOk();
        $this->assertSame('DAY', $res->json($date)[0]['slot']);
        $this->assertStringNotContainsString('রহিম', $res->getContent());
    }

    public function test_only_admin_can_change_rates(): void
    {
        $body = [
            'name' => 'হল', 'capacity' => 200, 'security_deposit' => 3000, 'advance_percent' => 50,
            'free_cancel_days' => 5, 'late_cancel_refund_percent' => 25, 'is_active' => true,
            'rates' => [['slot' => 'DAY', 'start_time' => '10:00', 'end_time' => '15:00', 'resident_rate' => 7000, 'outsider_rate' => 14000]],
        ];

        $this->actingAs($this->user('manager'), 'sanctum')->putJson('/api/hall', $body)->assertForbidden();

        $this->actingAs($this->user('admin'), 'sanctum')->putJson('/api/hall', $body)->assertOk()
            ->assertJsonPath('rates.0.resident_rate', '7000.0000');
        $this->assertSame('3000.0000', CommunityHall::first()->security_deposit);
    }

    public function test_tenant_cannot_use_the_office_endpoints(): void
    {
        $this->actingAs($this->user('tenant'), 'sanctum');

        $this->getJson('/api/hall/bookings')->assertForbidden();
        $this->postJson('/api/hall/bookings', $this->payload())->assertForbidden();
    }

    // ---- resident portal

    private function resident(): array
    {
        $person = Person::factory()->create();
        $user = User::factory()->create(['person_id' => $person->id, 'role' => 'flat_owner']);
        $user->assignRole('flat_owner');
        $flat = Flat::factory()->create();
        FlatParty::create(['flat_id' => $flat->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2026-01-01']);

        return [$user, $flat];
    }

    public function test_resident_requests_a_booking_at_the_resident_rate_and_it_waits_for_approval(): void
    {
        [$user, $flat] = $this->resident();

        $res = $this->actingAs($user, 'sanctum')
            ->postJson('/api/me/hall/bookings', $this->payload(['flat_id' => $flat->id, 'discount' => 9999]))
            ->assertCreated();

        $this->assertSame('PENDING', $res->json('status'));
        $this->assertSame('10000.0000', $res->json('total_amount')); // resident rate; own discount ignored
    }

    public function test_resident_cannot_book_for_a_flat_that_is_not_theirs(): void
    {
        [$user] = $this->resident();
        $other = Flat::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/me/hall/bookings', $this->payload(['flat_id' => $other->id]))
            ->assertStatus(422)->assertJsonValidationErrors('flat_id');
    }

    public function test_resident_sees_only_their_own_bookings_and_can_cancel_a_pending_one(): void
    {
        [$user, $flat] = $this->resident();
        [$stranger, $strangerFlat] = $this->resident();

        $this->actingAs($stranger, 'sanctum')->postJson('/api/me/hall/bookings', $this->payload(['flat_id' => $strangerFlat->id, 'booking_date' => $this->date(25)]))->assertCreated();
        $mine = $this->actingAs($user, 'sanctum')->postJson('/api/me/hall/bookings', $this->payload(['flat_id' => $flat->id]))->assertCreated();

        $list = $this->actingAs($user, 'sanctum')->getJson('/api/me/hall/bookings')->assertOk();
        $this->assertCount(1, $list->json());

        $this->postJson('/api/me/hall/bookings/'.$mine->json('id').'/cancel')->assertOk()->assertJsonPath('status', 'CANCELLED');
    }

    public function test_resident_cannot_cancel_someone_elses_request_or_a_confirmed_one(): void
    {
        [$user, $flat] = $this->resident();
        [$stranger] = $this->resident();
        $booking = $this->confirmed(['flat_id' => $flat->id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/me/hall/bookings/'.$booking->id.'/cancel')->assertStatus(422);
        $this->actingAs($stranger, 'sanctum')->postJson('/api/me/hall/bookings/'.$booking->id.'/cancel')->assertStatus(422);
    }
}
