<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseHead;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The yearly budget against what was really spent. The fiscal year runs July to June (BR-02), and "spent" is
 * always worked out from the approved expenses - never stored - so it cannot drift from the books.
 * The report also carries last year's figures for comparison and the month-by-month spending.
 */
class BudgetService
{
    /** The fiscal year (by its starting year) a date falls in. */
    public static function fiscalYearOf(Carbon $date): int
    {
        return $date->month >= 7 ? $date->year : $date->year - 1;
    }

    /** July first .. June thirtieth of the fiscal year that starts in $fiscalYear. */
    private function range(int $fiscalYear): array
    {
        return [Carbon::create($fiscalYear, 7, 1)->toDateString(), Carbon::create($fiscalYear + 1, 6, 30)->toDateString()];
    }

    /** Approved spending per head per month of a fiscal year: [head_id => [12 amounts, July first]]. */
    private function monthly(int $fiscalYear): array
    {
        [$from, $to] = $this->range($fiscalYear);
        $out = [];
        foreach (Expense::where('status', 'APPROVED')->whereBetween('date', [$from, $to])->get(['expense_head_id', 'date', 'amount']) as $e) {
            // July = slot 0 ... June = slot 11
            $slot = ($e->date->month + 5) % 12;
            $out[$e->expense_head_id] ??= array_fill(0, 12, '0');
            $out[$e->expense_head_id][$slot] = bcadd($out[$e->expense_head_id][$slot], (string) $e->amount, 4);
        }

        return $out;
    }

    private function sum(array $twelve): string
    {
        return array_reduce($twelve, fn ($c, $v) => bcadd($c, $v, 4), '0');
    }

    /**
     * @return array<string, mixed>
     */
    public function report(int $fiscalYear): array
    {
        [$from, $to] = $this->range($fiscalYear);

        $budgets = Budget::where('fiscal_year', $fiscalYear)->pluck('amount', 'expense_head_id');
        $lastBudgets = Budget::where('fiscal_year', $fiscalYear - 1)->pluck('amount', 'expense_head_id');
        $months = $this->monthly($fiscalYear);
        $lastMonths = $this->monthly($fiscalYear - 1);

        $rows = [];
        $monthTotals = array_fill(0, 12, '0');
        $tot = ['budget' => '0', 'spent' => '0', 'last_budget' => '0', 'last_spent' => '0'];
        foreach (ExpenseHead::orderBy('code')->get() as $head) {
            $m = $months[$head->id] ?? array_fill(0, 12, '0');
            $b = bcadd((string) ($budgets[$head->id] ?? 0), '0', 4);
            $s = $this->sum($m);
            $lb = bcadd((string) ($lastBudgets[$head->id] ?? 0), '0', 4);
            $ls = $this->sum($lastMonths[$head->id] ?? array_fill(0, 12, '0'));

            $rows[] = [
                'expense_head_id' => $head->id, 'code' => $head->code, 'name_bn' => $head->name_bn, 'name_en' => $head->name_en,
                'budget' => $b, 'spent' => $s, 'remaining' => bcsub($b, $s, 4),
                'percent' => bccomp($b, '0', 4) > 0 ? round((float) $s / (float) $b * 100, 1) : null,
                'over' => bccomp($s, $b, 4) > 0 && (bccomp($b, '0', 4) > 0 || bccomp($s, '0', 4) > 0),
                'last_budget' => $lb, 'last_spent' => $ls,
                // how this year's spending compares with all of last year's (null when last year had none)
                'spent_change_percent' => bccomp($ls, '0', 4) > 0 ? round(((float) $s - (float) $ls) / (float) $ls * 100, 1) : null,
                'monthly' => $m,
            ];
            foreach ($m as $i => $v) {
                $monthTotals[$i] = bcadd($monthTotals[$i], $v, 4);
            }
            $tot['budget'] = bcadd($tot['budget'], $b, 4);
            $tot['spent'] = bcadd($tot['spent'], $s, 4);
            $tot['last_budget'] = bcadd($tot['last_budget'], $lb, 4);
            $tot['last_spent'] = bcadd($tot['last_spent'], $ls, 4);
        }

        // the twelve months with their calendar month number, July first, plus an even share of the budget for reference
        $monthList = [];
        foreach ($monthTotals as $i => $v) {
            $monthList[] = ['month' => ($i + 6) % 12 + 1, 'year' => $fiscalYear + ($i >= 6 ? 1 : 0), 'spent' => $v];
        }

        return [
            'fiscal_year' => $fiscalYear,
            'label' => $fiscalYear.'-'.substr((string) ($fiscalYear + 1), 2),
            'last_label' => ($fiscalYear - 1).'-'.substr((string) $fiscalYear, 2),
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'months' => $monthList,
            'monthly_budget_share' => bcdiv($tot['budget'], '12', 4),
            'totals' => [
                'budget' => $tot['budget'], 'spent' => $tot['spent'], 'remaining' => bcsub($tot['budget'], $tot['spent'], 4),
                'percent' => bccomp($tot['budget'], '0', 4) > 0 ? round((float) $tot['spent'] / (float) $tot['budget'] * 100, 1) : null,
                'last_budget' => $tot['last_budget'], 'last_spent' => $tot['last_spent'],
                'spent_change_percent' => bccomp($tot['last_spent'], '0', 4) > 0 ? round(((float) $tot['spent'] - (float) $tot['last_spent']) / (float) $tot['last_spent'] * 100, 1) : null,
            ],
        ];
    }

    /** @param  list<array{expense_head_id:int, amount:numeric-string}>  $items */
    public function save(int $fiscalYear, array $items): void
    {
        DB::transaction(function () use ($fiscalYear, $items) {
            foreach ($items as $item) {
                Budget::updateOrCreate(
                    ['fiscal_year' => $fiscalYear, 'expense_head_id' => $item['expense_head_id']],
                    ['amount' => $item['amount']],
                );
            }
        });
    }

    /**
     * Start a year's budget from another year's: every head's amount is copied, optionally raised (or cut) by a
     * percentage. Heads that already have an amount in the target year are left alone unless $overwrite is set,
     * so a half-typed budget is never wiped by accident.
     *
     * @return int number of heads written
     */
    public function copy(int $fromYear, int $toYear, string $percent = '0', bool $overwrite = false): int
    {
        if ($fromYear === $toYear) {
            throw ValidationException::withMessages(['to_year' => 'একই বছর থেকে কপি করা যায় না।']);
        }
        $source = Budget::where('fiscal_year', $fromYear)->where('amount', '>', 0)->get();
        if ($source->isEmpty()) {
            throw ValidationException::withMessages(['from_year' => 'ঐ বছরে কোনো বাজেট বসানো নেই।']);
        }

        return DB::transaction(function () use ($source, $toYear, $percent, $overwrite) {
            $factor = bcadd('1', bcdiv($percent, '100', 8), 8);
            $written = 0;
            foreach ($source as $b) {
                $existing = Budget::where('fiscal_year', $toYear)->where('expense_head_id', $b->expense_head_id)->first();
                if ($existing && bccomp((string) $existing->amount, '0', 4) > 0 && ! $overwrite) {
                    continue;
                }
                $amount = bcmul((string) $b->amount, $factor, 4);
                // round to a whole taka: budgets are plans, not ledger entries
                $amount = bcadd($amount, '0.5', 0);
                Budget::updateOrCreate(['fiscal_year' => $toYear, 'expense_head_id' => $b->expense_head_id], ['amount' => $amount]);
                $written++;
            }

            return $written;
        });
    }
}
