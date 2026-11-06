<?php

namespace App\Services;

use App\Models\FlatParty;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentStatusNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Tells a flat's people - its current owners and tenants who have a login - what happened to their payment.
 * It never blocks the office's work: a missing table or any failure is logged and the payment stands as it is.
 */
class ResidentNotifier
{
    /** @return \Illuminate\Support\Collection<int, User> the active logins of everyone living in or owning the flat */
    public function usersOfFlat(int $flatId)
    {
        $personIds = FlatParty::where('flat_id', $flatId)->whereNull('to_date')->pluck('person_id')->unique();

        return $personIds->isEmpty() ? collect() : User::whereIn('person_id', $personIds)->where('status', 'ACTIVE')->get();
    }

    public function paymentApproved(Payment $payment): int
    {
        $payment->loadMissing('flat');

        return $this->send($payment, true,
            'আপনার পেমেন্ট গ্রহণ করা হয়েছে',
            sprintf('ফ্ল্যাট %s — ৳%s (%s)। রসিদ নম্বর: %s', $payment->flat?->flat_no, number_format((float) $payment->amount, 2), $payment->payment_date?->format('d/m/Y'), $payment->receipt_no),
        );
    }

    public function paymentRejected(Payment $payment, string $reason): int
    {
        $payment->loadMissing('flat');

        return $this->send($payment, false,
            'আপনার পেমেন্ট গ্রহণ করা হয়নি',
            sprintf('ফ্ল্যাট %s — ৳%s (%s)। কারণ: %s', $payment->flat?->flat_no, number_format((float) $payment->amount, 2), $payment->payment_date?->format('d/m/Y'), $reason),
        );
    }

    private function send(Payment $payment, bool $accepted, string $title, string $body): int
    {
        if (! Schema::hasTable('notifications')) {
            return 0;
        }
        $sent = 0;
        try {
            foreach ($this->usersOfFlat((int) $payment->flat_id) as $user) {
                // the id is only a pointer: a rejected payment is gone, so it has none to open
                $user->notify(new PaymentStatusNotification($accepted, $title, $body, $accepted ? $payment->id : null));
                $sent++;
            }
        } catch (\Throwable $e) {
            Log::warning('Payment notice failed: '.$e->getMessage());
        }

        return $sent;
    }
}
