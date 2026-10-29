<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The code is deployed before the database upgrade is imported (shared host, no CLI). In that window the site
 * must keep working: no audit table and no settings table must never break a login, a save or a bill run.
 */
class UpgradeInBetweenTest extends TestCase
{
    use RefreshDatabase;

    private function forgetTables(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('app_settings');
        $ready = new \ReflectionProperty(AuditObserver::class, 'ready');
        $ready->setAccessible(true);
        $ready->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $ready = new \ReflectionProperty(AuditObserver::class, 'ready');
        $ready->setAccessible(true);
        $ready->setValue(null, null);
        parent::tearDown();
    }

    public function test_saving_records_works_without_the_audit_table(): void
    {
        $this->forgetTables();
        $user = User::factory()->create(['role' => 'admin']);
        $user->update(['last_login_at' => now()]);
        $flat = Flat::factory()->create();
        $flat->update(['service_charge_rate' => 4000]);

        $this->assertSame(4000, (int) $flat->fresh()->service_charge_rate);
    }

    public function test_settings_fall_back_to_their_defaults_without_the_settings_table(): void
    {
        $this->forgetTables();
        $settings = app(SettingsService::class);

        $this->assertSame(10, $settings->int('bill_due_day'));
        $this->assertFalse($settings->bool('sms_enabled'));
        $this->assertSame('NONE', $settings->get('late_fee_mode'));
    }
}
