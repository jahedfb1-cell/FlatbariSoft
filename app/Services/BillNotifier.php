<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\FlatParty;
use App\Models\User;
use App\Notifications\BillReadyNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Tells a flat's people - its current owners and tenants who have a login - that the month's bill is ready.
 * It never stops billing: if the notifications table is missing or anything fails, the bills stand and the failure is logged.
 */
class BillNotifier
{
    private const MONTHS = ['', 'জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];

    /** @return int how many notifications were created */
    public function billsGenerated(BillingPeriod $period): int
    {
        if (! Schema::hasTable('notifications')) {
            return 0;
        }
        $sent = 0;
        try {
            $month = self::MONTHS[$period->month].' '.$period->year;
            Bill::with('flat')->where('billing_period_id', $period->id)->orderBy('id')->each(function (Bill $bill) use ($month, &$sent) {
                $personIds = FlatParty::where('flat_id', $bill->flat_id)->whereNull('to_date')->pluck('person_id')->unique();
                if ($personIds->isEmpty()) {
                    return;
                }
                $users = User::whereIn('person_id', $personIds)->where('status', 'ACTIVE')->get();
                if ($users->isEmpty()) {
                    return;
                }
                $due = $bill->due_date ? ', শেষ তারিখ '.$bill->due_date->format('d/m/Y') : '';
                $body = sprintf('ফ্ল্যাট %s — মোট প্রদেয় ৳%s%s।', $bill->flat->flat_no, number_format((float) $bill->total_payable, 2), $due);
                $note = new BillReadyNotification($bill->id, "{$month} মাসের বিল তৈরি হয়েছে", $body);
                foreach ($users as $user) {
                    $user->notify($note);
                    $sent++;
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Bill notifications failed: '.$e->getMessage());
        }

        return $sent;
    }
}
