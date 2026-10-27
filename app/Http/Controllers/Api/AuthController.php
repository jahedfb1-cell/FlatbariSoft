<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

    /**
     * Second login path (owner-requested): email + password, for admin-side
     * accounts that were given an email/password instead of relying on
     * OTP for every login. Mobile+OTP (above) remains the only path for
     * flat_owner/tenant accounts, which never get an email.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->where('status', 'ACTIVE')->first();

        // Same generic message whether the email doesn't exist or the
        // password is wrong — never reveal which one it was.
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'ইমেইল অথবা পাসওয়ার্ড সঠিক নয়।',
            ], 422);
        }

        $user->update(['last_login' => now()]);

        return response()->json([
            'token' => $user->createToken('auth')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Self-service password change for the logged-in user (either login
     * path lands here — mobile+OTP users may still set a real password
     * so the email+password path becomes available to them later, once
     * an admin gives them an email).
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'বর্তমান পাসওয়ার্ড সঠিক নয়।',
            ], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        // Force re-login everywhere else — only the token used to make
        // this request stays valid, same principle as a "log out other
        // devices" action. currentAccessToken() is null when there is no
        // real Sanctum token on the request (e.g. session-guard access);
        // in that case there is nothing to spare, so revoke everything.
        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))->delete();

        return response()->json(['message' => 'পাসওয়ার্ড পরিবর্তন হয়েছে।']);
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
