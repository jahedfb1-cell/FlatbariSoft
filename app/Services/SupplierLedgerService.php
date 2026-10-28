<?php

namespace App\Services;

use App\Models\ConstructionMaterialPurchase;
use App\Models\GasPurchase;
use Illuminate\Support\Collection;

/**
 * Suppliers of gas ('gas') and of building construction material ('materials'), and what is owed to each.
 * Every purchase is a CREDIT in the supplier's account (we owe the bill); it is settled by a DEBIT when the
 * matching expense is approved and the money has gone out of cash or bank. So the balance is exactly what has
 * been bought but not yet paid. Suppliers are told apart by their name ignoring case and extra spaces, so
 * "Bashundhara LP Gas" and "bashundhara lp gas " are one account. Everything is derived from the purchases on
 * every call - nothing is stored.
 */
class SupplierLedgerService
{
    public const KINDS = ['gas', 'materials'];

    /** The key two spellings of one supplier share. */
    public static function key(?string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $name)));
    }

    /** @return Collection<int, GasPurchase|ConstructionMaterialPurchase> */
    private function all(string $kind): Collection
    {
        $model = $kind === 'materials' ? ConstructionMaterialPurchase::class : GasPurchase::class;

        return $model::with('expense')->orderBy('date')->orderBy('id')->get();
    }

    private function ref(string $kind, $p): string
    {
        return ($kind === 'materials' ? 'CM-' : 'GP-').str_pad((string) $p->id, 6, '0', STR_PAD_LEFT);
    }

    private function isPaid($p): bool
    {
        return $p->expense?->status === 'APPROVED';
    }

    /** Gas is also counted in kilograms; material comes in mixed units, so no quantity total is kept for it. */
    private function kg(string $kind, $p): string
    {
        return $kind === 'gas' ? (string) $p->quantity_kg : '0';
    }

    private function nameOf(Collection $purchases, string $fallback = '—'): string
    {
        // show the spelling used most often
        return $purchases->countBy(fn ($p) => trim((string) $p->supplier_name))->sortDesc()->keys()->first() ?: $fallback;
    }

    /**
     * One row per supplier: how much was bought, paid and is still owed.
     *
     * @return array{kind:string, suppliers:list<array<string,mixed>>, totals:array<string,string>}
     */
    public function suppliers(string $kind, ?string $from = null, ?string $to = null): array
    {
        $groups = $this->all($kind)
            ->when($from, fn ($c) => $c->filter(fn ($p) => $p->date->toDateString() >= $from))
            ->when($to, fn ($c) => $c->filter(fn ($p) => $p->date->toDateString() <= $to))
            ->groupBy(fn ($p) => self::key($p->supplier_name));

        $rows = [];
        $totals = ['count' => '0', 'quantity_kg' => '0', 'billed' => '0', 'paid' => '0', 'owed' => '0'];

        foreach ($groups as $key => $purchases) {
            $kg = $billed = $paid = '0';
            foreach ($purchases as $p) {
                $kg = bcadd($kg, $this->kg($kind, $p), 4);
                $billed = bcadd($billed, (string) $p->total_cost, 4);
                if ($this->isPaid($p)) {
                    $paid = bcadd($paid, (string) $p->total_cost, 4);
                }
            }
            $rows[] = [
                'key' => $key,
                'name' => $this->nameOf($purchases),
                'count' => $purchases->count(),
                'quantity_kg' => $kg,
                'billed' => $billed,
                'paid' => $paid,
                'owed' => bcsub($billed, $paid, 4),
                'avg_cost_per_kg' => bccomp($kg, '0', 4) > 0 ? bcdiv($billed, $kg, 4) : '0.0000',
                'last_purchase' => $purchases->max(fn ($p) => $p->date->toDateString()),
            ];
            $totals['count'] = bcadd($totals['count'], (string) $purchases->count(), 0);
            $totals['quantity_kg'] = bcadd($totals['quantity_kg'], $kg, 4);
            $totals['billed'] = bcadd($totals['billed'], $billed, 4);
            $totals['paid'] = bcadd($totals['paid'], $paid, 4);
            $totals['owed'] = bcadd($totals['owed'], bcsub($billed, $paid, 4), 4);
        }

        usort($rows, fn ($a, $b) => bccomp($b['owed'], $a['owed'], 4) ?: strcmp($a['name'], $b['name']));

        return ['kind' => $kind, 'suppliers' => $rows, 'totals' => array_map(fn ($v) => bcadd($v, '0', 4), $totals)];
    }

    /**
     * One supplier's account in date order: purchases (credit), payments (debit) and the running balance owed.
     * With $from, everything before it is rolled into an opening balance.
     */
    public function ledger(string $kind, string $supplier, ?string $from = null, ?string $to = null): array
    {
        $key = self::key($supplier);
        $purchases = $this->all($kind)->filter(fn ($p) => self::key($p->supplier_name) === $key)->values();

        // two events per purchase: the bill, and its payment once approved
        $events = [];
        foreach ($purchases as $p) {
            $events[] = ['date' => $p->date->toDateString(), 'order' => 0, 'id' => $p->id, 'type' => 'PURCHASE', 'p' => $p, 'credit' => (string) $p->total_cost, 'debit' => '0'];
            if ($this->isPaid($p)) {
                $events[] = ['date' => $p->expense->date->toDateString(), 'order' => 1, 'id' => $p->id, 'type' => 'PAYMENT', 'p' => $p, 'credit' => '0', 'debit' => (string) $p->total_cost];
            }
        }
        usort($events, fn ($a, $b) => [$a['date'], $a['order'], $a['id']] <=> [$b['date'], $b['order'], $b['id']]);

        $opening = '0';
        $balance = '0';
        $rows = [];
        $credit = $debit = $kg = '0';
        foreach ($events as $e) {
            $net = bcsub($e['credit'], $e['debit'], 4);
            if ($from && $e['date'] < $from) {
                $opening = bcadd($opening, $net, 4);
                $balance = $opening;

                continue;
            }
            if ($to && $e['date'] > $to) {
                continue;
            }
            $balance = bcadd($balance, $net, 4);
            $p = $e['p'];
            $credit = bcadd($credit, $e['credit'], 4);
            $debit = bcadd($debit, $e['debit'], 4);
            if ($e['type'] === 'PURCHASE') {
                $kg = bcadd($kg, $this->kg($kind, $p), 4);
            }
            $rows[] = [
                'date' => $e['date'],
                'type' => $e['type'],
                'ref' => $this->ref($kind, $p),
                'purchase_id' => $p->id,
                'particulars' => $e['type'] === 'PURCHASE'
                    ? $this->describe($kind, $p)
                    : 'Paid by '.($p->expense->treasury_medium === 'BANK' ? 'bank' : 'cash').($p->expense->voucher_no ? ' — voucher '.$p->expense->voucher_no : ''),
                'voucher_no' => $p->expense?->voucher_no,
                'quantity_kg' => $e['type'] === 'PURCHASE' && $kind === 'gas' ? $p->quantity_kg : null,
                'credit' => $e['credit'],
                'debit' => $e['debit'],
                'balance' => $balance,
            ];
        }

        return [
            'kind' => $kind,
            'supplier' => $this->nameOf($purchases, $supplier),
            'from' => $from,
            'to' => $to,
            'opening' => bcadd($opening, '0', 4),
            'rows' => $rows,
            'totals' => ['purchased' => $credit, 'paid' => $debit, 'quantity_kg' => $kg, 'closing' => bcadd($balance, '0', 4)],
        ];
    }

    private function describe(string $kind, $p): string
    {
        $transport = (float) $p->transport_cost > 0 ? ' + transport '.$this->num($p->transport_cost) : '';

        return $kind === 'materials'
            ? sprintf('%s — %s %s @ %s%s', $p->item_name, $this->num($p->quantity), $p->unit, $this->num($p->unit_rate), $transport)
            : sprintf('Gas purchase — %s kg @ %s%s', $this->num($p->quantity_kg), $this->num($p->unit_price), $transport);
    }

    private function num(mixed $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }
}
