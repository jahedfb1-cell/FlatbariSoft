<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\Flat;
use App\Models\FlatParty;
use App\Models\Person;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\BillingAutomationService;
use App\Services\SettingsService;
use Carbon\Carbon;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\OperationsPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Settings, the daily late fee / reminder run, and the SMS gateway. Everything is off until the office says so. */
class BillingAutomationTest extends TestCase
{
    use RefreshDatabase;

    private SettingsService $settings;

    private BillingAutomationService $auto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(OperationsPermissionSeeder::class);
        $this->settings = app(SettingsService::class);
        $this->auto = app(BillingAutomationService::class);
        SmsTemplate::create(['event_key' => 'DUE_REMINDER', 'body_bn' => 'Flat {{flat_no}} owes {{amount}}', 'is_active' => true]);
    }

    private function bill(string $due, string $total = '1000', string $paid = '0'): Bill
    {
        $flat = Flat::factory()->create();
        $period = BillingPeriod::firstOrCreate(['year' => 2026, 'month' => 9], ['status' => 'GENERATED']);

        return Bill::create([
            'billing_period_id' => $period->id, 'flat_id' => $flat->id, 'service_charge' => $total, 'gas_amount' => 0, 'other_charges' => 0,
            'late_fee' => 0, 'previous_due' => 0, 'total_payable' => $total, 'paid_amount' => $paid, 'due_date' => $due,
            'status' => $paid > 0 ? 'PARTIAL' : 'UNPAID',
        ]);
    }

    private function resident(Flat $flat, string $mobile): void
    {
        $person = Person::create(['name_bn' => 'Resident', 'name_en' => 'Resident', 'mobile' => $mobile]);
        FlatParty::create(['flat_id' => $flat->id, 'person_id' => $person->id, 'role' => 'OWNER', 'from_date' => '2026-01-01']);
    }

    public function test_nothing_happens_until_switched_on(): void
    {
        $this->bill('2026-09-10');
        $this->assertSame(0, $this->auto->applyLateFees(Carbon::parse('2026-10-20')));
        $this->assertSame(0, $this->auto->sendReminders(Carbon::parse('2026-10-20')));
        $this->assertSame(0, SmsLog::count());
    }

    public function test_percent_late_fee_is_added_once_on_the_unpaid_part(): void
    {
        $this->settings->update(['late_fee_mode' => 'PERCENT', 'late_fee_value' => '5', 'late_fee_grace_days' => '5']);
        $late = $this->bill('2026-09-10', '1000', '400');   // 600 unpaid -> 30
        $this->bill('2026-10-18');                            // not yet due
        $this->bill('2026-10-12');                            // inside the grace days on 2026-10-15

        $today = Carbon::parse('2026-10-15');
        $this->assertSame(1, $this->auto->applyLateFees($today));
        $this->assertSame('30.0000', $late->fresh()->late_fee);
        $this->assertSame('1030.0000', $late->fresh()->total_payable);

        // running it again fines nobody twice
        $this->assertSame(0, $this->auto->applyLateFees($today));
        $this->assertSame('1030.0000', $late->fresh()->total_payable);
    }

    public function test_fixed_late_fee_and_paid_bills_are_left_alone(): void
    {
        $this->settings->update(['late_fee_mode' => 'FIXED', 'late_fee_value' => '200']);
        $unpaid = $this->bill('2026-09-10');
        $paid = $this->bill('2026-09-10', '1000', '1000');
        $paid->update(['status' => 'PAID']);

        $this->assertSame(1, $this->auto->applyLateFees(Carbon::parse('2026-10-15')));
        $this->assertSame('200.0000', $unpaid->fresh()->late_fee);
        $this->assertSame('0.0000', $paid->fresh()->late_fee);
    }

    public function test_reminders_log_but_do_not_send_while_sms_is_off(): void
    {
        $this->settings->update(['reminders_enabled' => true, 'reminder_days_before_due' => 3, 'reminder_repeat_days' => 7]);
        Http::fake();
        $bill = $this->bill('2026-10-12', '1500');
        $this->resident($bill->flat, '01711111111');

        $this->assertSame(1, $this->auto->sendReminders(Carbon::parse('2026-10-10')));
        $log = SmsLog::first();
        $this->assertSame('QUEUED_PENDING_GATEWAY', $log->status);
        $this->assertStringContainsString('1,500', $log->message);
        Http::assertNothingSent();
    }

    public function test_reminders_repeat_only_after_the_interval_and_skip_flats_with_nobody_to_text(): void
    {
        $this->settings->update(['reminders_enabled' => true, 'reminder_days_before_due' => 3, 'reminder_repeat_days' => 7]);
        $bill = $this->bill('2026-10-12');
        $this->bill('2026-10-12');  // no resident, so nobody to text
        $this->resident($bill->flat, '01711111111');

        $this->assertSame(1, $this->auto->sendReminders(Carbon::parse('2026-10-10')));
        $this->assertSame(0, $this->auto->sendReminders(Carbon::parse('2026-10-12')), 'too soon to remind again');
        $bill->update(['reminded_at' => Carbon::parse('2026-10-10')]);
        $this->assertSame(1, $this->auto->sendReminders(Carbon::parse('2026-10-18')));
    }

    public function test_the_http_gateway_sends_when_sms_is_on_and_marks_failures(): void
    {
        config(['sms.driver' => 'http', 'sms.url' => 'https://sms.test/send?k={key}&s={sender}&to={to}&msg={message}', 'sms.key' => 'KEY', 'sms.sender' => 'GV', 'sms.success' => '202']);
        $this->settings->update(['sms_enabled' => true]);

        Http::fake(['sms.test/*' => Http::sequence()->push('{"response_code":202}', 200)->push('{"response_code":1007}', 200)]);
        $sms = app(\App\Services\SmsService::class);

        $ok = $sms->send('01711111111', 'হ্যালো');
        $this->assertSame('SENT', $ok->status);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'to=8801711111111') && str_contains($r->url(), 'k=KEY') && str_contains($r->url(), rawurlencode('হ্যালো')));

        $bad = $sms->send('01722222222', 'x');
        $this->assertSame('FAILED', $bad->status);
    }

    public function test_settings_endpoints_need_the_permission_and_validate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $api = $this->actingAs($admin, 'sanctum');

        $api->getJson('/api/settings')->assertOk()->assertJsonPath('settings.late_fee_mode', 'NONE')->assertJsonPath('sms_gateway', 'NOT_CONFIGURED');
        $api->putJson('/api/settings', ['late_fee_mode' => 'PERCENT', 'late_fee_value' => '2.5', 'bill_due_day' => 15])->assertOk()->assertJsonPath('settings.bill_due_day', '15');
        $api->putJson('/api/settings', ['late_fee_mode' => 'WHATEVER'])->assertStatus(422);
        $api->putJson('/api/settings', ['bill_due_day' => 40])->assertStatus(422);

        $nobody = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($nobody, 'sanctum')->getJson('/api/settings')->assertForbidden();
    }
}
