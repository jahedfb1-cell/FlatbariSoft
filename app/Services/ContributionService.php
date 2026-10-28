<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Contribution;
use App\Models\ContributionHead;
use App\Models\ContributionSetting;
use App\Models\Flat;
use App\Models\FlatContributionTarget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Money collected from flat owners toward the building's construction cost: subscription, gas line,
 * generator, old water dues, the east wall, ... Each flat has a target (a default, overridable per flat);
 * what is still owed is always target minus what has been collected under the heads that count toward it,
 * derived on every call - nothing mutable is stored.
 *
 * New receipts are posted like every other receipt (cash/bank line + a balanced journal entry, credited to
 * the Capital Fund 3200 - it is capital, not income). Money collected before this system existed is loaded
 * as "opening" rows that only move the flat's balance and never touch cash, bank or the journal.
 */
class ContributionService
{
    private const CAPITAL_ACCOUNT = '3200';

    public function __construct(private readonly AccountingService $accounting) {}

    public function targetFor(Flat $flat): string
    {
        $own = FlatContributionTarget::where('flat_id', $flat->id)->value('target_amount');

        return bcadd((string) ($own ?? ContributionSetting::current()->default_target), '0', 4);
    }

    /** $amount null removes the flat's own target, so it falls back to the default. */
    public function setFlatTarget(Flat $flat, ?string $amount, ?string $note = null): void
    {
        if ($amount === null) {
            FlatContributionTarget::where('flat_id', $flat->id)->delete();

            return;
        }
        FlatContributionTarget::updateOrCreate(['flat_id' => $flat->id], ['target_amount' => $amount, 'note' => $note]);
    }

    public function setDefaultTarget(string $amount): ContributionSetting
    {
        $setting = ContributionSetting::current();
        $setting->update(['default_target' => $amount]);

        return $setting->fresh();
    }

    /** $data: contribution_head_id, amount, received_on, method (not OPENING), reference_no?, notes? */
    public function record(Flat $flat, array $data, int $by): Contribution
    {
        $amount = (string) $data['amount'];
        if (bccomp($amount, '0', 4) <= 0) {
            throw ValidationException::withMessages(['amount' => 'পরিমাণ শূন্যের বেশি হতে হবে।']);
        }

        return DB::transaction(function () use ($flat, $data, $amount, $by) {
            $head = ContributionHead::where('is_active', true)->findOrFail($data['contribution_head_id']);

            $row = Contribution::create([
                'flat_id' => $flat->id,
                'contribution_head_id' => $head->id,
                'amount' => $amount,
                'received_on' => $data['received_on'],
                'method' => $data['method'],
                'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $by,
            ]);
            $row->update(['receipt_no' => 'CC-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT)]);

            $particulars = "নির্মাণ ব্যয় আদায় — {$flat->flat_no} — {$head->name_bn} ({$row->receipt_no})";
            $this->post($row, $particulars, in: true, by: $by);

            return $row->fresh();
        });
    }

    /** Undoes a receipt with an opposite row (and opposite ledger lines); the original is never touched. */
    public function reverse(Contribution $contribution, string $reason, int $by): Contribution
    {
        return DB::transaction(function () use ($contribution, $reason, $by) {
            $original = Contribution::whereKey($contribution->id)->lockForUpdate()->firstOrFail();

            if (bccomp((string) $original->amount, '0', 4) <= 0) {
                throw ValidationException::withMessages(['contribution' => 'এই এন্ট্রি আগেই বাতিলের এন্ট্রি।']);
            }
            if (Contribution::where('reverses_id', $original->id)->exists()) {
                throw ValidationException::withMessages(['contribution' => 'এই এন্ট্রি ইতিমধ্যে বাতিল করা হয়েছে।']);
            }

            $row = Contribution::create([
                'flat_id' => $original->flat_id,
                'contribution_head_id' => $original->contribution_head_id,
                'amount' => bcmul((string) $original->amount, '-1', 4),
                'received_on' => Carbon::now('Asia/Dhaka')->toDateString(),
                'method' => $original->method,
                'reverses_id' => $original->id,
                'notes' => "বাতিল: {$reason} ({$original->receipt_no})",
                'created_by' => $by,
            ]);
            $row->update(['receipt_no' => 'CC-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT)]);

            if ($original->journal_entry_id) { // an opening row has no ledger lines to undo
                $this->post($row, "বাতিল {$original->receipt_no} ({$row->receipt_no})", in: false, by: $by);
            }

            return $row->fresh();
        });
    }

    /**
     * Pasted rows from the manager's sheet: [['flat_no' => '10A', 'amounts' => ['SUBSCRIPTION' => 2450000, ...]], ...].
     * Everything is checked first; if any flat or head is unknown nothing is saved. Loaded as OPENING rows.
     *
     * @return array{rows:int, entries:int, total:string, problems:array<int,string>}
     */
    public function importOpening(array $rows, string $asOf, int $by, bool $dryRun = false): array
    {
        $heads = ContributionHead::pluck('id', 'code');
        $flats = Flat::pluck('id', 'flat_no');
        $problems = [];
        $entries = [];

        foreach ($rows as $i => $row) {
            $no = trim((string) ($row['flat_no'] ?? ''));
            if (! isset($flats[$no])) {
                $problems[] = 'লাইন '.($i + 1).": ফ্ল্যাট '{$no}' পাওয়া যায়নি।";

                continue;
            }
            foreach ($row['amounts'] ?? [] as $code => $amount) {
                $amount = (string) $amount;
                if ($amount === '' || bccomp($amount, '0', 4) === 0) {
                    continue;
                }
                if (! isset($heads[$code])) {
                    $problems[] = 'লাইন '.($i + 1).": খাত '{$code}' অজানা।";

                    continue;
                }
                if (bccomp($amount, '0', 4) < 0) {
                    $problems[] = 'লাইন '.($i + 1).": '{$no}'-এর {$code} ঋণাত্মক।";

                    continue;
                }
                $entries[] = ['flat_id' => $flats[$no], 'head_id' => $heads[$code], 'amount' => $amount];
            }
        }

        $total = array_reduce($entries, fn ($c, $e) => bcadd($c, $e['amount'], 4), '0');
        $summary = ['rows' => count($rows), 'entries' => count($entries), 'total' => bcadd($total, '0', 4), 'problems' => $problems];

        if ($problems) {
            throw ValidationException::withMessages(['rows' => $problems]);
        }
        if ($dryRun) {
            return $summary;
        }

        DB::transaction(function () use ($entries, $asOf, $by) {
            foreach ($entries as $e) {
                $row = Contribution::create([
                    'flat_id' => $e['flat_id'],
                    'contribution_head_id' => $e['head_id'],
                    'amount' => $e['amount'],
                    'received_on' => $asOf,
                    'method' => 'OPENING',
                    'notes' => 'শুরুর জমা (সিস্টেমে আসার আগে আদায়)',
                    'created_by' => $by,
                ]);
                $row->update(['receipt_no' => 'CC-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT)]);
            }
        });

        return $summary;
    }

    /**
     * One row per flat: collected per head, total counted, target, and what is left.
     * balance > 0 = still owed; balance < 0 = paid more than the target.
     */
    public function statement(): array
    {
        $heads = ContributionHead::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $sums = Contribution::selectRaw('flat_id, contribution_head_id, SUM(amount) as total')
            ->groupBy('flat_id', 'contribution_head_id')->get()
            ->groupBy('flat_id');
        $own = FlatContributionTarget::pluck('target_amount', 'flat_id');
        $default = (string) ContributionSetting::current()->default_target;

        // top floor first, then A, B, C... as on the manager's sheet ("10A" before "9A")
        $flats = Flat::with(['currentOwners.person'])->get()->sort(function (Flat $a, Flat $b) {
            $fa = (int) $a->flat_no;
            $fb = (int) $b->flat_no;

            return $fa === $fb ? strcmp($a->flat_no, $b->flat_no) : $fb <=> $fa;
        })->values();

        $rows = [];
        $totals = array_fill_keys($heads->pluck('code')->all(), '0') + ['collected' => '0', 'target' => '0', 'balance_due' => '0', 'excess' => '0'];

        foreach ($flats as $flat) {
            $byHead = ($sums[$flat->id] ?? collect())->keyBy('contribution_head_id');
            $perHead = [];
            $counted = '0';
            foreach ($heads as $h) {
                $v = bcadd((string) ($byHead[$h->id]->total ?? 0), '0', 4);
                $perHead[$h->code] = $v;
                $totals[$h->code] = bcadd($totals[$h->code], $v, 4);
                if ($h->counts_toward_target) {
                    $counted = bcadd($counted, $v, 4);
                }
            }
            $target = bcadd((string) ($own[$flat->id] ?? $default), '0', 4);
            $balance = bcsub($target, $counted, 4);

            $rows[] = [
                'flat_id' => $flat->id,
                'flat_no' => $flat->flat_no,
                'owner' => $flat->currentOwners->map(fn ($p) => $p->person?->name_en ?: $p->person?->name_bn)->filter()->implode(', ') ?: null,
                'heads' => $perHead,
                'collected' => $counted,
                'target' => $target,
                'has_own_target' => $own->has($flat->id),
                'balance' => $balance,
                'status' => bccomp($balance, '0', 4) > 0 ? 'DUE' : (bccomp($balance, '0', 4) < 0 ? 'EXCESS' : 'CLEARED'),
            ];

            $totals['collected'] = bcadd($totals['collected'], $counted, 4);
            $totals['target'] = bcadd($totals['target'], $target, 4);
            $bucket = bccomp($balance, '0', 4) > 0 ? 'balance_due' : 'excess';
            $totals[$bucket] = bcadd($totals[$bucket], bccomp($balance, '0', 4) > 0 ? $balance : bcmul($balance, '-1', 4), 4);
        }

        return [
            'default_target' => bcadd($default, '0', 4),
            'heads' => $heads->map(fn ($h) => ['code' => $h->code, 'name_bn' => $h->name_bn, 'name_en' => $h->name_en, 'id' => $h->id, 'counts_toward_target' => $h->counts_toward_target])->values()->all(),
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * One flat owner's ledger: the target, then every receipt in date order with what is still owed after
     * it. Reversal rows appear as negative lines, so the running balance always matches the books.
     *
     * @return array<string, mixed>
     */
    public function ledger(Flat $flat): array
    {
        $flat->loadMissing('currentOwners.person');
        $target = $this->targetFor($flat);
        $balance = $target;
        $counted = '0';
        $entries = [];

        $rows = Contribution::with('head')->where('flat_id', $flat->id)->orderBy('received_on')->orderBy('id')->get();
        $reversed = $rows->whereNotNull('reverses_id')->pluck('reverses_id')->all();

        foreach ($rows as $c) {
            if ($c->head->counts_toward_target) {
                $counted = bcadd($counted, (string) $c->amount, 4);
                $balance = bcsub($target, $counted, 4);
            }
            $entries[] = [
                'id' => $c->id,
                'receipt_no' => $c->receipt_no,
                'date' => $c->received_on->toDateString(),
                'head_code' => $c->head->code,
                'head_name_bn' => $c->head->name_bn,
                'head_name_en' => $c->head->name_en,
                'counts_toward_target' => $c->head->counts_toward_target,
                'method' => $c->method,
                'reference_no' => $c->reference_no,
                'notes' => $c->notes,
                'amount' => $c->amount,
                'balance_after' => $balance,
                'is_reversal' => $c->reverses_id !== null,
                'is_reversed' => in_array($c->id, $reversed, true),
            ];
        }

        return [
            'flat_id' => $flat->id,
            'flat_no' => $flat->flat_no,
            'owner' => $flat->currentOwners->map(fn ($p) => $p->person?->name_en ?: $p->person?->name_bn)->filter()->implode(', ') ?: null,
            'target' => $target,
            'collected' => bcadd($counted, '0', 4),
            'balance' => bcsub($target, $counted, 4), // > 0 owed, < 0 paid more than the target
            'entries' => $entries,
        ];
    }

    /** Every flat's ledger, in sheet order (top floor first). */
    public function allLedgers(): array
    {
        return Flat::with('currentOwners.person')->get()->sort(function (Flat $a, Flat $b) {
            $fa = (int) $a->flat_no;
            $fb = (int) $b->flat_no;

            return $fa === $fb ? strcmp($a->flat_no, $b->flat_no) : $fb <=> $fa;
        })->values()->map(fn (Flat $f) => $this->ledger($f))->all();
    }

    /** Every receipt for one flat, newest first, with a reversal marker. */
    public function history(Flat $flat): array
    {
        $reversed = Contribution::where('flat_id', $flat->id)->whereNotNull('reverses_id')->pluck('reverses_id')->all();

        return Contribution::with('head')->where('flat_id', $flat->id)->orderByDesc('received_on')->orderByDesc('id')->get()
            ->map(fn (Contribution $c) => [
                'id' => $c->id,
                'receipt_no' => $c->receipt_no,
                'head_code' => $c->head->code,
                'head_name_bn' => $c->head->name_bn,
                'head_name_en' => $c->head->name_en,
                'amount' => $c->amount,
                'received_on' => $c->received_on->toDateString(),
                'method' => $c->method,
                'reference_no' => $c->reference_no,
                'notes' => $c->notes,
                'is_reversal' => $c->reverses_id !== null,
                'is_reversed' => in_array($c->id, $reversed, true),
            ])->all();
    }

    /** Cash/bank line + journal entry, in the direction asked for. Callers hold the transaction. */
    private function post(Contribution $row, string $particulars, bool $in, int $by): void
    {
        if ($row->method === 'OPENING') {
            return;
        }
        $amount = bcadd(ltrim((string) $row->amount, '-'), '0', 4);
        $ledger = [
            'txn_date' => $row->received_on->toDateString(),
            'particulars' => $particulars,
            'fund_type' => 'CAPITAL',
            'ref_type' => Contribution::class,
            'ref_id' => $row->id,
            'created_by' => $by,
        ];

        if ($row->method === 'CASH') {
            $this->accounting->postCashTransaction($ledger + ($in ? ['in_amount' => $amount] : ['out_amount' => $amount]));
            $asset = ChartOfAccount::where('code', '1200')->firstOrFail();
        } else {
            $bank = BankAccount::first();
            if (! $bank) {
                throw ValidationException::withMessages(['bank' => 'কোনো ব্যাংক হিসাব সেটআপ করা নেই।']);
            }
            $this->accounting->postBankTransaction($bank, $ledger + ($in ? ['debit' => $amount] : ['credit' => $amount]));
            $asset = ChartOfAccount::where('code', '1100')->firstOrFail();
        }

        $capital = ChartOfAccount::where('code', self::CAPITAL_ACCOUNT)->firstOrFail();
        $lines = $in
            ? [['account_id' => $asset->id, 'debit' => $amount], ['account_id' => $capital->id, 'credit' => $amount]]
            : [['account_id' => $capital->id, 'debit' => $amount], ['account_id' => $asset->id, 'credit' => $amount]];

        $entry = $this->accounting->postJournal($lines, $particulars, $row->received_on->toDateString(), $by, $by);
        $row->update(['journal_entry_id' => $entry->id]);
    }
}
