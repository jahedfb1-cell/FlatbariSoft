<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    /**
     * Send an OTP to a mobile number that belongs to an active account.
     * Accounts are created by an admin (Phase 0.5+) — there is no public
     * self-signup, so an unknown/inactive number is rejected here.
     */
    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $user = User::where('mobile', $request->mobile)->where('status', 'ACTIVE')->first();

        if (! $user) {
            return response()->json([
                'message' => 'এই মোবাইল নম্বরে কোনো সক্রিয় অ্যাকাউন্ট নেই।',
            ], 404);
        }

        $code = $this->otpService->generateAndSend($user->mobile);

        return response()->json([
            'message' => 'OTP পাঠানো হয়েছে।',
            // Only ever exposed outside production — real delivery is the
            // SMS gateway from Phase 3.1.
            'dev_otp' => app()->environment(['local', 'testing']) ? $code : null,
        ]);
    }

    /**
     * Verify the OTP and issue a Sanctum token.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user = User::where('mobile', $request->mobile)->where('status', 'ACTIVE')->first();

        if (! $user) {
            return response()->json([
                'message' => 'এই মোবাইল নম্বরে কোনো সক্রিয় অ্যাকাউন্ট নেই।',
            ], 404);
        }

        $result = $this->otpService->verify($request->mobile, $request->code);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        $user->update(['last_login' => now()]);

        return response()->json([
            'token' => $user->createToken('auth')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'লগআউট হয়েছে।']);
    }
}
