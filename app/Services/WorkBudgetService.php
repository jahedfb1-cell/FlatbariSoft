<?php

namespace App\Services;

use App\Models\ContributionBook;
use App\Models\Expense;
use App\Models\Flat;
use App\Models\FlatContributionTarget;
use App\Models\WorkBudget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Budgets for one-off pieces of work. Not every flat pays, and not equally - so a work starts from a split the
 * committee picks (equal, by service-charge rate, by size, or by hand) over the flats they choose, and every flat's
 * share can then be set freely. Each share is that flat's target in the work's own contribution book, so receipts,
 * ledgers and "who is still due" all come from the existing collection books; expenses are linked to the work.
 */
class WorkBudgetService
{
    public function __construct(private readonly ContributionService $contributions) {}

    /**
     * The proposed share of each chosen flat. The sum equals the estimate exactly (the rounding remainder goes
     * to the last flat); MANUAL proposes nothing, every share is typed in.
     *
     * @param  list<int>|null  $flatIds  null = every active flat
     * @return list<array{flat_id:int, flat_no:string, owner:?string, tenant:?string, weight:string, amount:string}>
     */
    public function preview(string $method, string $estimated, ?array $flatIds): array
    {
        $flats = Flat::with('currentOwners.person', 'currentTenant.person')->where('status', 'ACTIVE')->when($flatIds !== null, fn ($q) => $q->whereIn('id', $flatIds))->get()
            ->sortBy('flat_no', SORT_NATURAL)->values();

        $weight = fn (Flat $f): string => match ($method) {
            'SERVICE_RATE' => (string) ($f->service_charge_rate ?? 0),
            'SIZE' => (string) ($f->size_sqft ?? 0),
            'EQUAL' => '1',
            default => '0',
        };

        $total = '0';
        foreach ($flats as $f) {
            $total = bcadd($total, $weight($f), 4);
        }
        if ($method !== 'MANUAL' && bccomp($total, '0', 4) <= 0) {
            throw ValidationException::withMessages(['split_method' => 'এই পদ্ধতিতে ভাগ করার মতো তথ্য (রেট/আকার) ফ্ল্যাটগুলোতে নেই — অন্য পদ্ধতি বাছুন।']);
        }

        $rows = [];
        $given = '0';
        foreach ($flats as $i => $f) {
            $w = $weight($f);
            if ($method === 'MANUAL') {
                $amount = '0.00';
            } elseif ($i === $flats->count() - 1) {
                $amount = bcsub($estimated, $given, 2); // the rounding remainder lands on the last flat
            } else {
                $amount = bcdiv(bcmul($estimated, $w, 6), $total, 2);
            }
            $given = bcadd($given, $amount, 2);
            $rows[] = [
                'flat_id' => $f->id, 'flat_no' => $f->flat_no, 'owner' => $f->occupantNames()['owner'], 'tenant' => $f->occupantNames()['tenant'],
                'weight' => $w, 'amount' => $amount,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $data  title, description?, estimated_cost, start_date?, end_date?, status?, split_method, resolution_id?
     * @param  list<array{flat_id:int, amount:string|int|float, note?:?string}>  $shares
     */
    public function create(array $data, array $shares, int $by): WorkBudget
    {
        return DB::transaction(function () use ($data, $shares, $by) {
            $book = ContributionBook::create([
                'code' => 'WORK_'.strtoupper(Str::random(8)),
                'name_bn' => $data['title'], 'name_en' => $data['title'],
                'default_target' => 0, // only the flats given a share owe anything
                'credit_account_code' => '3200',
                'sort_order' => 100,
            ]);
            DB::table('contribution_heads')->insert([
                'contribution_book_id' => $book->id, 'code' => $book->code, 'name_bn' => 'আদায়', 'name_en' => 'Collection',
                'counts_toward_target' => true, 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $work = WorkBudget::create([
                'contribution_book_id' => $book->id, 'title' => $data['title'], 'description' => $data['description'] ?? null,
                'estimated_cost' => $data['estimated_cost'], 'start_date' => $data['start_date'] ?? null, 'end_date' => $data['end_date'] ?? null,
                'status' => $data['status'] ?? 'PLANNED', 'split_method' => $data['split_method'], 'resolution_id' => $data['resolution_id'] ?? null,
                'created_by' => $by,
            ]);
            $this->setShares($work, $shares);

            return $work->fresh();
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(WorkBudget $work, array $data): WorkBudget
    {
        $work->update(array_intersect_key($data, array_flip(['title', 'description', 'estimated_cost', 'start_date', 'end_date', 'status', 'resolution_id'])));
        if (isset($data['title'])) {
            $work->book->update(['name_bn' => $data['title'], 'name_en' => $data['title']]);
        }

        return $work->fresh();
    }

    /**
     * Replaces the work's shares: flats listed get exactly that share (0 = waived), flats not listed are taken off.
     * What a flat has already paid is never touched.
     *
     * @param  list<array{flat_id:int, amount:string|int|float, note?:?string}>  $shares
     */
    public function setShares(WorkBudget $work, array $shares): void
    {
        DB::transaction(function () use ($work, $shares) {
            $keep = [];
            foreach ($shares as $s) {
                $keep[] = (int) $s['flat_id'];
                FlatContributionTarget::updateOrCreate(
                    ['flat_id' => (int) $s['flat_id'], 'contribution_book_id' => $work->contribution_book_id],
                    ['target_amount' => bcadd((string) $s['amount'], '0', 4), 'note' => $s['note'] ?? null],
                );
            }
            FlatContributionTarget::where('contribution_book_id', $work->contribution_book_id)->whereNotIn('flat_id', $keep)->delete();
        });
    }

    /** @return array<string, mixed> */
    public function summary(WorkBudget $work): array
    {
        $statement = $this->contributions->statement($work->book);
        $rows = array_values(array_filter($statement['rows'], fn ($r) => $r['has_own_target']));

        $planned = $statement['totals']['target'];
        $collected = $statement['totals']['collected'];
        $spent = (string) (Expense::where('work_budget_id', $work->id)->where('status', 'APPROVED')->sum('amount') ?: 0);
        $pending = (string) (Expense::where('work_budget_id', $work->id)->where('status', 'PENDING')->sum('amount') ?: 0);
        $estimated = bcadd((string) $work->estimated_cost, '0', 4);

        return [
            'id' => $work->id, 'title' => $work->title, 'description' => $work->description, 'status' => $work->status,
            'start_date' => $work->start_date?->toDateString(), 'end_date' => $work->end_date?->toDateString(),
            'split_method' => $work->split_method, 'resolution_id' => $work->resolution_id, 'book_code' => $work->book->code,
            'estimated' => $estimated,
            'planned' => bcadd($planned, '0', 4),                          // what the flats were asked for, in total
            'gap' => bcsub($estimated, $planned, 4),                       // estimate not yet covered by anyone's share (negative = over-asked)
            'collected' => bcadd($collected, '0', 4),
            'still_to_collect' => bcadd($statement['totals']['balance_due'], '0', 4),
            'spent' => bcadd($spent, '0', 4),
            'pending_spend' => bcadd($pending, '0', 4),
            'in_hand' => bcsub($collected, $spent, 4),                      // collected for this work, less what was spent on it
            'budget_left' => bcsub($estimated, $spent, 4),
            'percent_spent' => bccomp($estimated, '0', 4) > 0 ? (float) bcmul(bcdiv($spent, $estimated, 6), '100', 1) : null,
            'over_budget' => bccomp($spent, $estimated, 4) > 0,
            'spent_more_than_collected' => bccomp($spent, $collected, 4) > 0,
            'flats' => count($rows),
            'rows' => array_map(fn ($r) => [
                'flat_id' => $r['flat_id'], 'flat_no' => $r['flat_no'], 'owner' => $r['owner'], 'tenant' => $r['tenant'],
                'share' => $r['target'], 'paid' => $r['collected'], 'due' => $r['balance'], 'status' => $r['status'],
            ], $rows),
        ];
    }

    /** What an expense linked to a work may not do: be added to a closed work, or push the approved spending past the estimate. */
    public function checkExpense(WorkBudget $work, string $amount, ?int $exceptExpenseId = null, bool $forApproval = false): void
    {
        if (! $work->isOpen()) {
            throw ValidationException::withMessages(['work_budget_id' => 'এই কাজ সমাপ্ত বা বাতিল — এতে নতুন খরচ যোগ বা অনুমোদন করা যাবে না।']);
        }
        if ($forApproval) {
            $spent = (string) (Expense::where('work_budget_id', $work->id)->where('status', 'APPROVED')->when($exceptExpenseId, fn ($q) => $q->where('id', '!=', $exceptExpenseId))->sum('amount') ?: 0);
            if (bccomp(bcadd($spent, $amount, 4), (string) $work->estimated_cost, 4) > 0) {
                throw ValidationException::withMessages(['amount' => 'এই খরচ অনুমোদন করলে কাজের বাজেট ('.bcadd((string) $work->estimated_cost, '0', 2).') পেরিয়ে যাবে (এ পর্যন্ত খরচ '.bcadd($spent, '0', 2).')। আগে কাজের প্রাক্কলিত খরচ বাড়ান।']);
            }
        }
    }
}
