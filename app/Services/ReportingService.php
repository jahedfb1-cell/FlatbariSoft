<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\GasConsumer;
use App\Models\JournalLine;
use App\Models\Payment;

class ReportingService
{
    /**
     * Every account's net position (debit-normal accounts show a debit
     * balance, credit-normal accounts show a credit balance). Total debit
     * column always equals total credit column — that's not something
     * this method enforces, it's a consequence of AccountingService never
     * letting an unbalanced journal post in the first place.
     */
    public function trialBalance(): array
    {
        $rows = ChartOfAccount::query()
            ->leftJoin('journal_lines', 'journal_lines.account_id', '=', 'chart_of_accounts.id')
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name_bn', 'chart_of_accounts.name_en', 'chart_of_accounts.account_type')
            ->selectRaw('chart_of_accounts.id, chart_of_accounts.code, chart_of_accounts.name_bn, chart_of_accounts.name_en, chart_of_accounts.account_type,
                         COALESCE(SUM(journal_lines.debit),0) as total_debit, COALESCE(SUM(journal_lines.credit),0) as total_credit')
            ->havingRaw('SUM(journal_lines.debit) > 0 OR SUM(journal_lines.credit) > 0')
            ->orderBy('chart_of_accounts.code')
            ->get();

        $totalDebit = 0;
        $totalCredit = 0;
        $lines = [];

        foreach ($rows as $row) {
            $net = bcsub((string) $row->total_debit, (string) $row->total_credit, 4);
            $isDebitBalance = bccomp($net, '0', 4) >= 0;

            $lines[] = [
                'account_code' => $row->code,
                'account_name' => $row->name_bn,
                'account_name_en' => $row->name_en,
                'account_type' => $row->account_type,
                'debit' => $isDebitBalance ? abs((float) $net) : 0,
                'credit' => ! $isDebitBalance ? abs((float) $net) : 0,
            ];

            $totalDebit = bcadd($totalDebit, $isDebitBalance ? $net : '0', 4);
            $totalCredit = bcadd($totalCredit, ! $isDebitBalance ? bcmul($net, '-1', 4) : '0', 4);
        }

        return [
            'lines' => $lines,
            'total_debit' => (float) $totalDebit,
            'total_credit' => (float) $totalCredit,
            'balanced' => bccomp($totalDebit, $totalCredit, 4) === 0,
        ];
    }

    public function incomeStatement(string $fiscalYear): array
    {
        $sums = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.fiscal_year', $fiscalYear)
            ->where('journal_entries.status', 'POSTED')
            ->whereIn('chart_of_accounts.account_type', ['INCOME', 'EXPENSE'])
            ->groupBy('chart_of_accounts.account_type')
            ->selectRaw('chart_of_accounts.account_type, SUM(journal_lines.debit) as total_debit, SUM(journal_lines.credit) as total_credit')
            ->get()
            ->keyBy('account_type');

        $income = (float) bcsub((string) ($sums['INCOME']->total_credit ?? 0), (string) ($sums['INCOME']->total_debit ?? 0), 4);
        $expense = (float) bcsub((string) ($sums['EXPENSE']->total_debit ?? 0), (string) ($sums['EXPENSE']->total_credit ?? 0), 4);

        return [
            'fiscal_year' => $fiscalYear,
            'total_income' => $income,
            'total_expense' => $expense,
            'net_surplus' => $income - $expense,
        ];
    }

    public function balanceSheet(): array
    {
        $rows = ChartOfAccount::query()
            ->leftJoin('journal_lines', 'journal_lines.account_id', '=', 'chart_of_accounts.id')
            ->whereIn('chart_of_accounts.account_type', ['ASSET', 'LIABILITY', 'FUND', 'INCOME', 'EXPENSE'])
            ->groupBy('chart_of_accounts.account_type')
            ->selectRaw('chart_of_accounts.account_type, SUM(journal_lines.debit) as total_debit, SUM(journal_lines.credit) as total_credit')
            ->get()
            ->keyBy('account_type');

        $assets = (float) bcsub((string) ($rows['ASSET']->total_debit ?? 0), (string) ($rows['ASSET']->total_credit ?? 0), 4);
        $liabilities = (float) bcsub((string) ($rows['LIABILITY']->total_credit ?? 0), (string) ($rows['LIABILITY']->total_debit ?? 0), 4);
        $funds = (float) bcsub((string) ($rows['FUND']->total_credit ?? 0), (string) ($rows['FUND']->total_debit ?? 0), 4);

        // Income/Expense are temporary accounts — nothing in this app ever
        // posts a period-end closing entry moving their balance into a
        // Fund/equity account, so an interim balance sheet has to add
        // "current (unclosed) net income" itself to balance, the same way
        // any accrual-basis interim statement does.
        $income = (float) bcsub((string) ($rows['INCOME']->total_credit ?? 0), (string) ($rows['INCOME']->total_debit ?? 0), 4);
        $expense = (float) bcsub((string) ($rows['EXPENSE']->total_debit ?? 0), (string) ($rows['EXPENSE']->total_credit ?? 0), 4);
        $currentNetIncome = $income - $expense;

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'funds' => $funds,
            'current_net_income' => $currentNetIncome,
            'balanced' => abs($assets - ($liabilities + $funds + $currentNetIncome)) < 0.01,
        ];
    }

    public function dashboardKpis(int $year, int $month): array
    {
        $collection = Payment::where('status', 'APPROVED')
            ->whereYear('payment_date', $year)->whereMonth('payment_date', $month)
            ->sum('amount');

        $outstandingDue = Bill::where('status', '!=', 'PAID')
            ->selectRaw('SUM(total_payable - paid_amount) as total')
            ->value('total') ?? 0;

        $expense = Expense::where('status', 'APPROVED')
            ->whereYear('date', $year)->whereMonth('date', $month)
            ->sum('amount');

        $sinkingFund = ChartOfAccount::where('code', '3300')
            ->leftJoin('journal_lines', 'journal_lines.account_id', '=', 'chart_of_accounts.id')
            ->selectRaw('COALESCE(SUM(journal_lines.credit),0) - COALESCE(SUM(journal_lines.debit),0) as balance')
            ->value('balance') ?? 0;

        return [
            'collection' => (float) $collection,
            'outstanding_due' => (float) $outstandingDue,
            'expense' => (float) $expense,
            'sinking_fund_balance' => (float) $sinkingFund,
            'gas_consumer_count' => GasConsumer::where('is_internal', false)->where('active', true)->count(),
        ];
    }
}
