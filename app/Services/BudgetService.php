<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseHead;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The yearly budget against what was really spent. The fiscal year runs July to June (BR-02), and "spent" is
 * always worked out from the approved expenses - never stored - so it cannot drift from the books.
 */
class BudgetService
{
    /** The fiscal year (by its starting year) a date falls in. */
    public static function fiscalYearOf(Carbon $date): int
    {
        return $date->month >= 7 ? $date->year : $date->year - 1;
    }

    /**
     * @return array{fiscal_year:int, label:string, from:string, to:string, rows:list<array<string,mixed>>, totals:array<string,mixed>}
     */
    public function report(int $fiscalYear): array
    {
        $from = Carbon::create($fiscalYear, 7, 1)->toDateString();
        $to = Carbon::create($fiscalYear + 1, 6, 30)->toDateString();

        $budgets = Budget::where('fiscal_year', $fiscalYear)->pluck('amount', 'expense_head_id');
        $spent = Expense::where('status', 'APPROVED')->whereBetween('date', [$from, $to])
            ->selectRaw('expense_head_id, SUM(amount) total')->groupBy('expense_head_id')->pluck('total', 'expense_head_id');

        $rows = [];
        $sumBudget = $sumSpent = '0';
        foreach (ExpenseHead::orderBy('code')->get() as $head) {
            $b = bcadd((string) ($budgets[$head->id] ?? 0), '0', 4);
            $s = bcadd((string) ($spent[$head->id] ?? 0), '0', 4);
            $rows[] = [
                'expense_head_id' => $head->id, 'code' => $head->code, 'name_bn' => $head->name_bn, 'name_en' => $head->name_en,
                'budget' => $b, 'spent' => $s, 'remaining' => bcsub($b, $s, 4),
                'percent' => bccomp($b, '0', 4) > 0 ? round((float) $s / (float) $b * 100, 1) : null,
                'over' => bccomp($s, $b, 4) > 0 && (bccomp($b, '0', 4) > 0 || bccomp($s, '0', 4) > 0),
            ];
            $sumBudget = bcadd($sumBudget, $b, 4);
            $sumSpent = bcadd($sumSpent, $s, 4);
        }

        return [
            'fiscal_year' => $fiscalYear,
            'label' => $fiscalYear.'-'.substr((string) ($fiscalYear + 1), 2),
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => [
                'budget' => $sumBudget, 'spent' => $sumSpent, 'remaining' => bcsub($sumBudget, $sumSpent, 4),
                'percent' => bccomp($sumBudget, '0', 4) > 0 ? round((float) $sumSpent / (float) $sumBudget * 100, 1) : null,
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
}
