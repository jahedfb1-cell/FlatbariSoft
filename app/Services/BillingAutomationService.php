<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillingPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The things the office would otherwise do by hand every month, run by the daily schedule:
 * generate the month's bills, add the late fee to bills that missed their due date, and text a reminder
 * to flats with something still unpaid. Each is safe to run twice on the same day - a bill is never
 * fined twice, a flat is not reminded again before the repeat interval, a month is not generated twice.
 * Everything is switched by the office in Settings and is OFF until they do.
 */
class BillingAutomationService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly BillGenerationService $generator,
        private readonly SmsService $sms,
    ) {}

    /** Generate this month's bills on the 1st (only if switched on, and only if not already done). */
    public function generateMonth(?Carbon $today = null): ?BillingPeriod
    {
        $today ??= now('Asia/Dhaka');
        if (! $this->settings->bool('auto_generate_bills') || $today->day !== 1) {
            return null;
        }
        if (BillingPeriod::where('year', $today->year)->where('month', $today->month)->where('status', '!=', 'DRAFT')->exists()) {
            return null;
        }
        $by = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->value('id') ?? User::value('id');
        if (! $by) {
            return null;
        }

        return $this->generator->generate($today->year, $today->month, $by);
    }

    /**
     * Add the late fee, once, to every unpaid bill whose due date (plus the grace days) has passed.
     * Percent mode charges that percent of what is still unpaid on the bill; fixed mode a flat amount.
     *
     * @return int number of bills fined
     */
    public function applyLateFees(?Carbon $today = null): int
    {
        $mode = $this->settings->get('late_fee_mode');
        $value = $this->settings->get('late_fee_value');
        if ($mode === 'NONE' || bccomp($value, '0', 4) <= 0) {
            return 0;
        }
        $today = ($today ?? now('Asia/Dhaka'))->copy()->startOfDay();
        $cutoff = $today->copy()->subDays($this->settings->int('late_fee_grace_days'));

        $count = 0;
        Bill::where('status', '!=', 'PAID')->where('late_fee', 0)->whereDate('due_date', '<', $cutoff->toDateString())
            ->orderBy('id')->each(function (Bill $bill) use ($mode, $value, &$count) {
                DB::transaction(function () use ($bill, $mode, $value, &$count) {
                    $bill = Bill::whereKey($bill->id)->lockForUpdate()->first();
                    $unpaid = bcsub((string) $bill->total_payable, (string) $bill->paid_amount, 4);
                    if ($bill->status === 'PAID' || bccomp((string) $bill->late_fee, '0', 4) > 0 || bccomp($unpaid, '0', 4) <= 0) {
                        return;
                    }
                    $fee = $mode === 'PERCENT' ? bcdiv(bcmul($unpaid, $value, 6), '100', 4) : bcadd($value, '0', 4);
                    if (bccomp($fee, '0', 4) <= 0) {
                        return;
                    }
                    $bill->update(['late_fee' => $fee, 'total_payable' => bcadd((string) $bill->total_payable, $fee, 4)]);
                    $count++;
                });
            });

        return $count;
    }

    /**
     * Text the owner and tenant of every flat whose bill is unpaid and due within the warning window (or
     * already overdue), then not again until the repeat interval has passed.
     *
     * @return int number of bills reminded
     */
    public function sendReminders(?Carbon $today = null): int
    {
        if (! $this->settings->bool('reminders_enabled')) {
            return 0;
        }
        $today = ($today ?? now('Asia/Dhaka'))->copy()->startOfDay();
        $window = $today->copy()->addDays($this->settings->int('reminder_days_before_due'));
        $again = $today->copy()->subDays($this->settings->int('reminder_repeat_days'))->endOfDay();

        $count = 0;
        Bill::with('flat')->where('status', '!=', 'PAID')->whereDate('due_date', '<=', $window->toDateString())
            ->where(fn ($q) => $q->whereNull('reminded_at')->orWhere('reminded_at', '<=', $again))
            ->orderBy('id')->each(function (Bill $bill) use (&$count) {
                $due = bcsub((string) $bill->total_payable, (string) $bill->paid_amount, 4);
                if (bccomp($due, '0', 4) <= 0 || ! $bill->flat) {
                    return;
                }
                $sent = $this->sms->notifyFlat($bill->flat, 'DUE_REMINDER', ['amount' => number_format((float) $due, 0)]);
                if ($sent !== []) {
                    $bill->update(['reminded_at' => now()]);
                    $count++;
                }
            });

        return $count;
    }
}
