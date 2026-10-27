<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\CommunityHall;
use App\Models\HallBooking;
use App\Models\HallBookingPayment;
use App\Models\HallRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Community-hall bookings. Rates, deposit and the cancellation policy all
 * live in community_halls / hall_rates (editable), never in code.
 *
 * Money goes through the one real ledger like every other module: a payment
 * row is written together with its cash/bank ledger line and a balanced
 * journal entry, atomically. Rent is income (4600); the damage deposit is a
 * liability (2300) because it is handed back, so it never inflates income.
 */
class HallBookingService
{
    private const INCOME_ACCOUNT = '4600';

    private const DEPOSIT_ACCOUNT = '2300';

    /** The building is in Bangladesh: "today" is the Dhaka calendar day, not the server's UTC one. */
    private const TIMEZONE = 'Asia/Dhaka';

    public function __construct(private readonly AccountingService $accounting) {}

    private function today(): string
    {
        return Carbon::now(self::TIMEZONE)->toDateString();
    }

    public function hall(): CommunityHall
    {
        return CommunityHall::where('is_active', true)->with('rates')->orderBy('id')->firstOr(function () {
            throw ValidationException::withMessages(['hall' => 'কমিউনিটি হল সেটআপ করা নেই।']);
        });
    }

    /** DAY and NIGHT can share a date; FULL_DAY clashes with everything. */
    public static function slotsClash(string $a, string $b): bool
    {
        return $a === $b || $a === 'FULL_DAY' || $b === 'FULL_DAY';
    }

    /**
     * @return array{rent:string, security_deposit:string, start_time:string, end_time:string, is_resident:bool}
     */
    public function quote(string $slot, bool $isResident): array
    {
        $hall = $this->hall();
        /** @var HallRate|null $rate */
        $rate = $hall->rates->firstWhere('slot', $slot);
        $price = $rate ? ($isResident ? $rate->resident_rate : $rate->outsider_rate) : null;

        // NULL = the office hasn't decided yet. 0 is a real price: residents may use the hall free.
        if ($price === null) {
            throw ValidationException::withMessages(['slot' => 'এই সময়ের জন্য ভাড়ার রেট সেট করা নেই।']);
        }
        $rent = (string) $price;

        return [
            'rent' => $rent,
            'security_deposit' => (string) $hall->security_deposit,
            'start_time' => $rate->start_time,
            'end_time' => $rate->end_time,
            'is_resident' => $isResident,
        ];
    }

    /** Booked slots per date for a month ('2026-11'), for the calendar. Customer details are not exposed. */
    public function availability(string $month): array
    {
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $hall = $this->hall();

        $days = [];
        HallBooking::where('community_hall_id', $hall->id)
            ->whereBetween('booking_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->whereIn('status', HallBooking::ACTIVE_STATUSES)
            ->orderBy('booking_date')
            ->get(['booking_date', 'slot', 'status'])
            ->each(function (HallBooking $b) use (&$days) {
                $days[$b->booking_date->toDateString()][] = ['slot' => $b->slot, 'status' => $b->status];
            });

        return $days;
    }

    /**
     * $data: flat_id?, customer_name, customer_mobile, event_type, guest_count?, booking_date, slot,
     * notes?, extra_charge?, discount?. A booking taken by someone who manages the hall (walk-in at the
     * office) is confirmed straight away; a resident's request waits for approval.
     */
    public function create(array $data, int $requestedBy, bool $autoConfirm = false): HallBooking
    {
        $date = Carbon::parse($data['booking_date'])->startOfDay();
        if ($date->toDateString() < $this->today()) {
            throw ValidationException::withMessages(['booking_date' => 'অতীতের তারিখে বুকিং করা যাবে না।']);
        }

        return DB::transaction(function () use ($data, $requestedBy, $autoConfirm, $date) {
            // One hall row lock serialises bookings, so two people can't take the same slot at once.
            $hall = CommunityHall::whereKey($this->hall()->id)->lockForUpdate()->firstOrFail();
            $quote = $this->quote($data['slot'], ! empty($data['flat_id']));

            $this->assertSlotFree($hall->id, $date->toDateString(), $data['slot']);

            $extra = (string) ($data['extra_charge'] ?? 0);
            $discount = (string) ($data['discount'] ?? 0);
            $total = bcsub(bcadd($quote['rent'], $extra, 4), $discount, 4);
            if (bccomp($total, '0', 4) < 0) {
                throw ValidationException::withMessages(['discount' => 'ছাড় মোট ভাড়ার বেশি হতে পারে না।']);
            }

            $booking = HallBooking::create([
                'community_hall_id' => $hall->id,
                'flat_id' => $data['flat_id'] ?? null,
                'customer_name' => $data['customer_name'],
                'customer_mobile' => $data['customer_mobile'],
                'event_type' => $data['event_type'],
                'guest_count' => $data['guest_count'] ?? 0,
                'booking_date' => $date->toDateString(),
                'slot' => $data['slot'],
                'start_time' => $quote['start_time'],
                'end_time' => $quote['end_time'],
                'rent_amount' => $quote['rent'],
                'extra_charge' => $extra,
                'discount' => $discount,
                'total_amount' => $total,
                'security_deposit' => $quote['security_deposit'],
                'status' => $autoConfirm ? 'CONFIRMED' : 'PENDING',
                'notes' => $data['notes'] ?? null,
                'requested_by' => $requestedBy,
                'approved_by' => $autoConfirm ? $requestedBy : null,
                'decided_at' => $autoConfirm ? now() : null,
            ]);

            // Derived from the row's own id: unique and gap-free by construction, no counter to race on.
            $booking->update(['booking_no' => 'HB-'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT)]);

            return $booking->fresh();
        });
    }

    public function approve(HallBooking $booking, int $approvedBy): HallBooking
    {
        return DB::transaction(function () use ($booking, $approvedBy) {
            $locked = $this->lock($booking);
            $this->assertStatus($locked, ['PENDING'], 'শুধু অপেক্ষমাণ বুকিং অনুমোদন করা যায়।');

            $locked->update(['status' => 'CONFIRMED', 'approved_by' => $approvedBy, 'decided_at' => now()]);

            return $locked->fresh();
        });
    }

    public function reject(HallBooking $booking, string $reason, int $by): HallBooking
    {
        return DB::transaction(function () use ($booking, $reason, $by) {
            $locked = $this->lock($booking);
            $this->assertStatus($locked, ['PENDING'], 'শুধু অপেক্ষমাণ বুকিং বাতিল (reject) করা যায়।');

            $locked->update(['status' => 'REJECTED', 'status_reason' => $reason, 'approved_by' => $by, 'decided_at' => now()]);

            return $locked->fresh();
        });
    }

    public function complete(HallBooking $booking): HallBooking
    {
        return DB::transaction(function () use ($booking) {
            $locked = $this->lock($booking);
            $this->assertStatus($locked, ['CONFIRMED'], 'শুধু নিশ্চিত বুকিং সম্পন্ন করা যায়।');
            if ($locked->booking_date->toDateString() > $this->today()) {
                throw ValidationException::withMessages(['status' => 'অনুষ্ঠানের তারিখ আসার আগে বুকিং সম্পন্ন করা যাবে না।']);
            }

            $locked->update(['status' => 'COMPLETED']);

            return $locked->fresh();
        });
    }

    /**
     * Cancels and hands money back: the whole deposit, and the rent according to the hall's policy
     * (everything if cancelled early enough, otherwise the late-cancel share). $refundAmount lets the
     * office override the rent refund, but never above what was actually paid.
     */
    public function cancel(HallBooking $booking, string $reason, int $by, ?string $refundAmount = null, string $method = 'CASH'): HallBooking
    {
        return DB::transaction(function () use ($booking, $reason, $by, $refundAmount, $method) {
            $locked = $this->lock($booking);
            $this->assertStatus($locked, ['PENDING', 'CONFIRMED'], 'এই বুকিং আর বাতিল করা যাবে না।');

            $rentPaid = $locked->netPaid('RENT');
            $rentRefund = $refundAmount ?? $this->policyRefund($locked, $rentPaid);
            if (bccomp($rentRefund, $rentPaid, 4) > 0) {
                throw ValidationException::withMessages(['refund_amount' => 'ফেরত দেওয়া টাকা আদায়কৃত ভাড়ার বেশি হতে পারে না।']);
            }

            $locked->update(['status' => 'CANCELLED', 'status_reason' => $reason, 'decided_at' => now()]);

            if (bccomp($rentRefund, '0', 4) > 0) {
                $this->pay($locked, 'RENT_REFUND', $rentRefund, $method, $this->today(), null, $by);
            }
            $depositHeld = $locked->netPaid('DEPOSIT');
            if (bccomp($depositHeld, '0', 4) > 0) {
                $this->pay($locked, 'DEPOSIT_REFUND', $depositHeld, $method, $this->today(), null, $by);
            }

            return $locked->fresh('payments');
        });
    }

    /** The rent refund the hall's policy would give if this booking were cancelled today. */
    public function policyRefund(HallBooking $booking, ?string $rentPaid = null): string
    {
        $rentPaid ??= $booking->netPaid('RENT');
        $hall = $booking->hall;
        $daysAhead = Carbon::parse($this->today())->diffInDays(Carbon::parse($booking->booking_date->toDateString()), false);
        $percent = $daysAhead >= $hall->free_cancel_days ? '100' : (string) $hall->late_cancel_refund_percent;

        return bcdiv(bcmul($rentPaid, $percent, 6), '100', 4);
    }

    /**
     * $data: type (RENT|DEPOSIT|DEPOSIT_REFUND), amount, method, paid_on, reference_no?
     * Rent can't exceed the booking total, the deposit can't exceed the hall's deposit, and the
     * deposit is only handed back once the event is over.
     */
    public function recordPayment(HallBooking $booking, array $data, int $by): HallBookingPayment
    {
        return DB::transaction(function () use ($booking, $data, $by) {
            $locked = $this->lock($booking);
            $type = $data['type'];
            $amount = (string) $data['amount'];

            if ($type === 'RENT' || $type === 'DEPOSIT') {
                $this->assertStatus($locked, ['CONFIRMED', 'COMPLETED'], 'বুকিং নিশ্চিত হওয়ার পর টাকা নেওয়া যাবে।');
                $limit = $type === 'RENT' ? (string) $locked->total_amount : (string) $locked->security_deposit;
                $due = bcsub($limit, $locked->netPaid($type), 4);
                if (bccomp($amount, $due, 4) > 0) {
                    throw ValidationException::withMessages(['amount' => 'পরিমাণ বকেয়ার ('.$due.') বেশি হতে পারে না।']);
                }
            } elseif ($type === 'DEPOSIT_REFUND') {
                $this->assertStatus($locked, ['COMPLETED'], 'অনুষ্ঠান সম্পন্ন হওয়ার পর ডিপোজিট ফেরত দেওয়া যাবে।');
                if (bccomp($amount, $locked->netPaid('DEPOSIT'), 4) > 0) {
                    throw ValidationException::withMessages(['amount' => 'ফেরতের পরিমাণ জমা ডিপোজিটের বেশি হতে পারে না।']);
                }
            } else {
                throw ValidationException::withMessages(['type' => 'অবৈধ পেমেন্ট ধরন।']);
            }

            return $this->pay($locked, $type, $amount, $data['method'], $data['paid_on'], $data['reference_no'] ?? null, $by);
        });
    }

    /** Hall settings and the per-slot rates, as one edit. */
    public function updateSettings(CommunityHall $hall, array $data): CommunityHall
    {
        return DB::transaction(function () use ($hall, $data) {
            $hall->update(collect($data)->except('rates')->all());

            foreach ($data['rates'] ?? [] as $rate) {
                HallRate::updateOrCreate(
                    ['community_hall_id' => $hall->id, 'slot' => $rate['slot']],
                    [
                        'start_time' => $rate['start_time'],
                        'end_time' => $rate['end_time'],
                        'resident_rate' => $rate['resident_rate'],
                        'outsider_rate' => $rate['outsider_rate'],
                    ],
                );
            }

            return $hall->fresh('rates');
        });
    }

    private function assertSlotFree(int $hallId, string $date, string $slot): void
    {
        $clash = HallBooking::where('community_hall_id', $hallId)
            ->where('booking_date', $date)
            ->whereIn('status', HallBooking::ACTIVE_STATUSES)
            ->pluck('slot')
            ->contains(fn (string $taken) => self::slotsClash($taken, $slot));

        if ($clash) {
            throw ValidationException::withMessages(['slot' => 'এই তারিখে এই সময়ে হল ইতিমধ্যে বুক করা আছে।']);
        }
    }

    private function lock(HallBooking $booking): HallBooking
    {
        return HallBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
    }

    private function assertStatus(HallBooking $booking, array $allowed, string $message): void
    {
        if (! in_array($booking->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    /** One payment row + its cash/bank line + a balanced journal entry. Callers hold the transaction. */
    private function pay(HallBooking $booking, string $type, string $amount, string $method, string $paidOn, ?string $reference, int $by): HallBookingPayment
    {
        $payment = HallBookingPayment::create([
            'hall_booking_id' => $booking->id,
            'type' => $type,
            'amount' => $amount,
            'method' => $method,
            'paid_on' => $paidOn,
            'reference_no' => $reference,
            'created_by' => $by,
        ]);
        $payment->update(['receipt_no' => 'HR-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)]);

        $moneyIn = in_array($type, ['RENT', 'DEPOSIT'], true);
        $label = match ($type) {
            'RENT' => 'ভাড়া আদায়',
            'DEPOSIT' => 'সিকিউরিটি ডিপোজিট জমা',
            'RENT_REFUND' => 'ভাড়া ফেরত',
            'DEPOSIT_REFUND' => 'সিকিউরিটি ডিপোজিট ফেরত',
        };
        $particulars = "কমিউনিটি হল {$booking->booking_no} — {$label} ({$payment->receipt_no})";
        $ledger = [
            'txn_date' => $paidOn,
            'particulars' => $particulars,
            'fund_type' => 'OPERATING',
            'ref_type' => HallBookingPayment::class,
            'ref_id' => $payment->id,
            'created_by' => $by,
        ];

        if ($method === 'CASH') {
            $this->accounting->postCashTransaction($ledger + ($moneyIn ? ['in_amount' => $amount] : ['out_amount' => $amount]));
            $asset = ChartOfAccount::where('code', '1200')->firstOrFail();
        } else {
            $bank = BankAccount::first();
            if (! $bank) {
                throw ValidationException::withMessages(['bank' => 'কোনো ব্যাংক হিসাব সেটআপ করা নেই।']);
            }
            $this->accounting->postBankTransaction($bank, $ledger + ($moneyIn ? ['debit' => $amount] : ['credit' => $amount]));
            $asset = ChartOfAccount::where('code', '1100')->firstOrFail();
        }

        $other = ChartOfAccount::where('code', str_starts_with($type, 'DEPOSIT') ? self::DEPOSIT_ACCOUNT : self::INCOME_ACCOUNT)->firstOrFail();
        $lines = $moneyIn
            ? [['account_id' => $asset->id, 'debit' => $amount], ['account_id' => $other->id, 'credit' => $amount]]
            : [['account_id' => $other->id, 'debit' => $amount], ['account_id' => $asset->id, 'credit' => $amount]];

        $entry = $this->accounting->postJournal($lines, $particulars, $paidOn, $by, $by);
        $payment->update(['journal_entry_id' => $entry->id]);

        return $payment->fresh();
    }
}
