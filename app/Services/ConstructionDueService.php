<?php

namespace App\Services;

use App\Models\ConstructionDue;
use App\Models\DueInstallment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ConstructionDueService
{
    /**
     * @param  array<int, array{due_date:string, amount:float|string}>  $installments
     */
    public function create(array $data, array $installments): ConstructionDue
    {
        return DB::transaction(function () use ($data, $installments) {
            $due = ConstructionDue::create(array_merge($data, [
                'paid_amount' => 0,
                'balance' => $data['total_amount'],
            ]));

            foreach ($installments as $i => $installment) {
                $due->installments()->create([
                    'installment_no' => $i + 1,
                    'due_date' => $installment['due_date'],
                    'amount' => $installment['amount'],
                    'paid_amount' => 0,
                    'status' => 'UNPAID',
                ]);
            }

            return $due->load('installments');
        });
    }

    public function recordInstallmentPayment(DueInstallment $installment, string $amount): DueInstallment
    {
        return DB::transaction(function () use ($installment, $amount) {
            $newPaid = bcadd((string) $installment->paid_amount, $amount, 4);
            $status = match (true) {
                bccomp($newPaid, (string) $installment->amount, 4) >= 0 => 'PAID',
                bccomp($newPaid, '0', 4) > 0 => 'PARTIAL',
                default => 'UNPAID',
            };
            $installment->update(['paid_amount' => $newPaid, 'status' => $status]);

            $due = $installment->constructionDue()->lockForUpdate()->first();
            $duePaid = bcadd((string) $due->paid_amount, $amount, 4);
            $due->update([
                'paid_amount' => $duePaid,
                'balance' => max(0, (float) bcsub((string) $due->total_amount, $duePaid, 4)),
            ]);

            return $installment->fresh();
        });
    }

    /**
     * 0-30 / 31-90 / 90+ day aging buckets for unpaid/partial installments,
     * bucketed by how many days past due_date they already are.
     *
     * @return array{'0-30': float, '31-90': float, '90+': float}
     */
    public function agingReport(): array
    {
        $today = Carbon::today();
        $buckets = ['0-30' => 0.0, '31-90' => 0.0, '90+' => 0.0];

        DueInstallment::whereIn('status', ['UNPAID', 'PARTIAL'])
            ->where('due_date', '<=', $today)
            ->get()
            ->each(function (DueInstallment $installment) use (&$buckets, $today) {
                $outstanding = (float) bcsub((string) $installment->amount, (string) $installment->paid_amount, 4);
                // Carbon 3's diffInDays() returns a signed value by default
                // (negative when the argument is earlier) — abs() it, since
                // every row here already passed the due_date <= today
                // filter above and "how overdue" is never meaningfully signed.
                $daysOverdue = abs($today->diffInDays($installment->due_date));

                $bucket = match (true) {
                    $daysOverdue <= 30 => '0-30',
                    $daysOverdue <= 90 => '31-90',
                    default => '90+',
                };

                $buckets[$bucket] += $outstanding;
            });

        return $buckets;
    }
}
