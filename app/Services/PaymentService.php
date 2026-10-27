<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-17 Maker-Checker: Manager creates a payment (status=PENDING) — it
 * touches no ledger, no bank/cash balance, no bill balance until an
 * Admin/Super Admin approves it. Everything downstream (receipt number,
 * ledger posting, journal entry, bill status) happens atomically at
 * approval time, never at creation.
 */
class PaymentService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function create(array $data, int $receivedBy): Payment
    {
        return Payment::create(array_merge($data, [
            'received_by' => $receivedBy,
            'status' => 'PENDING',
        ]));
    }

    public function approve(Payment $payment, int $approvedBy): Payment
    {
        if ($payment->status === 'APPROVED') {
            throw ValidationException::withMessages(['status' => 'এই পেমেন্ট ইতিমধ্যে অনুমোদিত।']);
        }

        return DB::transaction(function () use ($payment, $approvedBy) {
            // Receipt number derives from this row's own auto-increment id
            // — unique and race-free by construction, no counter to lock.
            $payment->update([
                'status' => 'APPROVED',
                'approved_by' => $approvedBy,
                'receipt_no' => 'MR-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
            ]);

            $particulars = "Money Receipt {$payment->receipt_no} — Flat #{$payment->flat_id}";

            if ($payment->method === 'CASH') {
                $this->accounting->postCashTransaction([
                    'txn_date' => $payment->payment_date,
                    'particulars' => $particulars,
                    'in_amount' => $payment->amount,
                    'fund_type' => 'OPERATING',
                    'ref_type' => Payment::class,
                    'ref_id' => $payment->id,
                    'created_by' => $approvedBy,
                ]);
            } else {
                // BANK/BKASH/NAGAD all settle into the one operating bank
                // account — the payments table has no bank_account_id of
                // its own, and this is a single-building system with one
                // primary account in practice.
                $bank = BankAccount::first();
                if (! $bank) {
                    throw ValidationException::withMessages(['bank' => 'কোনো ব্যাংক হিসাব সেটআপ করা নেই।']);
                }
                $this->accounting->postBankTransaction($bank, [
                    'txn_date' => $payment->payment_date,
                    'particulars' => $particulars,
                    'debit' => $payment->amount,
                    'fund_type' => 'OPERATING',
                    'ref_type' => Payment::class,
                    'ref_id' => $payment->id,
                    'created_by' => $approvedBy,
                ]);
            }

            $assetAccount = ChartOfAccount::where('code', $payment->method === 'CASH' ? '1200' : '1100')->firstOrFail();
            $lines = $this->creditLinesFor($payment);

            $this->accounting->postJournal(
                array_merge(
                    [['account_id' => $assetAccount->id, 'debit' => $payment->amount]],
                    $lines,
                ),
                $particulars,
                $payment->payment_date,
                $approvedBy,
                $approvedBy,
            );

            if ($payment->bill_id) {
                $this->applyToBill($payment);
            }

            return $payment->fresh();
        });
    }

    /**
     * Splits the payment's credit side across income accounts using the
     * bill's own component amounts (service charge + previous due bundled
     * as one bucket, gas as the other) — a simplification, but the PRD's
     * own phrasing ("Credit: সংশ্লিষ্ট Income account", singular) doesn't
     * spell out a full waterfall allocation either.
     *
     * @return array<int, array{account_id:int, credit:string}>
     */
    private function creditLinesFor(Payment $payment): array
    {
        $amount = (string) $payment->amount;
        $bill = $payment->bill;

        if (! $bill) {
            return [['account_id' => ChartOfAccount::where('code', '4900')->firstOrFail()->id, 'credit' => $amount]];
        }

        $serviceBucket = bcadd((string) $bill->service_charge, (string) $bill->previous_due, 4);
        $gasBucket = (string) $bill->gas_amount;
        $total = bcadd($serviceBucket, $gasBucket, 4);

        $serviceIncome = ChartOfAccount::where('code', '4100')->firstOrFail();
        $gasIncome = ChartOfAccount::where('code', '4200')->firstOrFail();

        if (bccomp($total, '0', 4) <= 0) {
            return [['account_id' => $serviceIncome->id, 'credit' => $amount]];
        }

        $gasShare = bcdiv(bcmul($amount, $gasBucket, 10), $total, 4);
        $serviceShare = bcsub($amount, $gasShare, 4);

        $lines = [];
        if (bccomp($serviceShare, '0', 4) > 0) {
            $lines[] = ['account_id' => $serviceIncome->id, 'credit' => $serviceShare];
        }
        if (bccomp($gasShare, '0', 4) > 0) {
            $lines[] = ['account_id' => $gasIncome->id, 'credit' => $gasShare];
        }

        return $lines;
    }

    private function applyToBill(Payment $payment): void
    {
        /** @var Bill $bill */
        $bill = Bill::whereKey($payment->bill_id)->lockForUpdate()->first();

        $newPaid = bcadd((string) $bill->paid_amount, (string) $payment->amount, 4);
        $status = match (true) {
            bccomp($newPaid, (string) $bill->total_payable, 4) >= 0 => 'PAID',
            bccomp($newPaid, '0', 4) > 0 => 'PARTIAL',
            default => 'UNPAID',
        };

        $bill->update(['paid_amount' => $newPaid, 'status' => $status]);
    }
}
