<?php

namespace App\Services;

use App\Models\Otp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Generates and verifies mobile OTP codes.
 *
 * Delivery is a log line for now (dev-only) — Phase 3.1 introduces the real
 * SmsGatewayInterface/NullSmsGateway driver pattern. `deliver()` is the only
 * method that needs to change when that lands; generate/verify stay as-is.
 */
class OtpService
{
    /**
     * Generate a fresh OTP for a mobile number, invalidating any earlier
     * unconsumed code for that number, and deliver it.
     *
     * @return string the plain-text code (only for local/testing responses —
     *                 never log or return this outside a dev environment)
     */
    public function generateAndSend(string $mobile): string
    {
        // An earlier unconsumed OTP for this number is no longer usable once
        // a new one is requested — otherwise two valid codes could exist.
        Otp::where('mobile', $mobile)->whereNull('consumed_at')->delete();

        $code = str_pad(
            (string) random_int(0, (10 ** config('otp.length')) - 1),
            config('otp.length'),
            '0',
            STR_PAD_LEFT,
        );

        Otp::create([
            'mobile' => $mobile,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('otp.expiry_minutes')),
        ]);

        $this->deliver($mobile, $code);

        return $code;
    }

    /**
     * Verify a submitted code for a mobile number.
     *
     * @return array{success: bool, message: string}
     */
    public function verify(string $mobile, string $code): array
    {
        $otp = Otp::where('mobile', $mobile)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            return ['success' => false, 'message' => 'কোনো OTP পাওয়া যায়নি, নতুন করে অনুরোধ করুন।'];
        }

        if ($otp->expires_at->isPast()) {
            return ['success' => false, 'message' => 'OTP-এর মেয়াদ শেষ হয়ে গেছে, নতুন করে অনুরোধ করুন।'];
        }

        if ($otp->attempts >= config('otp.max_attempts')) {
            return ['success' => false, 'message' => 'সর্বোচ্চ সংখ্যক ভুল চেষ্টা হয়ে গেছে, নতুন করে অনুরোধ করুন।'];
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return ['success' => false, 'message' => 'ভুল OTP কোড।'];
        }

        $otp->update(['consumed_at' => now()]);

        return ['success' => true, 'message' => 'OTP যাচাই সফল হয়েছে।'];
    }

    private function deliver(string $mobile, string $code): void
    {
        Log::info("OTP for {$mobile}: {$code}");
    }
}
