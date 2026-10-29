<?php

namespace App\Providers;

use App\Contracts\SmsGatewayInterface;
use App\Services\Sms\NullSmsGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Phase 3.1 driver pattern: swap this binding for a real gateway
        // class later — SmsService and every caller never change.
        $this->app->bind(SmsGatewayInterface::class, fn () => config('sms.driver') === 'http' ? new \App\Services\Sms\HttpSmsGateway() : new NullSmsGateway());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // dompdf needs these two folders to exist and be writable, and a fresh clone/deploy has neither
        // (storage/app/* is not tracked) - create them on demand.
        File::ensureDirectoryExists((string) config('dompdf.options.font_cache'));
        File::ensureDirectoryExists((string) config('dompdf.options.temp_dir'));

        // The audit trail: every create / change / delete of the records that matter is written to audit_logs.
        foreach ([
            'Payment', 'Expense', 'Bill', 'Flat', 'User', 'Person', 'Tenant', 'Staff', 'SalaryPayment', 'Contribution',
            'FlatContributionTarget', 'TreasuryEntry', 'GasPurchase', 'ConstructionMaterialPurchase', 'Meeting', 'Resolution',
            'Complaint', 'Notice', 'HallBooking', 'HallBookingPayment', 'ParkingCharge', 'ParkingPayment', 'ConstructionDue',
            'Asset', 'BankAccount', 'AppSetting',
        ] as $audited) {
            ('App\\Models\\'.$audited)::observe(\App\Observers\AuditObserver::class);
        }

        // Every API Resource returns its fields at the top level, never
        // wrapped in a "data" key — consistent whether a resource is
        // returned directly from a controller or embedded inside a plain
        // array (e.g. AuthController::verifyOtp's ['token'=>..., 'user'=>...]).
        JsonResource::withoutWrapping();

        // Cap OTP requests per mobile number to stop someone from spamming
        // a number with codes (or, once Phase 3.1's real SMS gateway is
        // wired in, running up the SMS bill).
        RateLimiter::for('otp-request', function ($request) {
            return Limit::perMinute(3)->by($request->input('mobile', $request->ip()));
        });

        // Same idea for the email+password path — cap guesses per email so
        // it isn't brute-forceable, since (unlike OTP) the same password
        // stays valid across attempts.
        RateLimiter::for('login', function ($request) {
            return Limit::perMinute(5)->by($request->input('email', $request->ip()));
        });

        // PRD section 2: Super Admin can do "সব কিছু" (everything). Rather
        // than remembering to assign every permission created in every
        // future module to super_admin by hand, it bypasses permission
        // checks entirely — the standard spatie/laravel-permission pattern.
        Gate::before(fn ($user) => $user->hasRole('super_admin') ? true : null);
    }
}
