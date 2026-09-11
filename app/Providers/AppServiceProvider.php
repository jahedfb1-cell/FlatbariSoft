<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
    }
}
