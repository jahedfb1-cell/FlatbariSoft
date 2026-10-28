<?php

namespace App\Services;

use App\Models\GasPurchase;
use Illuminate\Support\Collection;

/**
 * The gas purchase ledger: every purchase in date order with a running total of kilograms and money,
 * the voucher it was booked under, and whether the supplier's bill photo is attached. All figures are
 * derived from the purchase rows on every request - nothing here is stored.
 */
class GasLedgerService
{
    public function __construct(private readonly InvoiceImageService $images) {}

    /** @return Collection<int, GasPurchase> */
    public function purchases(?string $from, ?string $to): Collection
    {
        return GasPurchase::with('expense')
            ->when($from, fn ($q) => $q->where('date', '>=', $from))
            ->when($to, fn ($q) => $q->where('date', '<=', $to))
            ->orderBy('date')->orderBy('id')
            ->get();
    }

    /**
     * @return array{
     *   from:?string, to:?string, rows:array<int,array<string,mixed>>, totals:array<string,mixed>,
     *   by_month:array<int,array<string,mixed>>, overall:array<string,mixed>
     * }
     */
    public function ledger(?string $from = null, ?string $to = null): array
    {
        $purchases = $this->purchases($from, $to);

        $runKg = '0';
        $runCost = '0';
        $rows = [];
        foreach ($purchases as $p) {
            $runKg = bcadd($runKg, (string) $p->quantity_kg, 4);
            $runCost = bcadd($runCost, (string) $p->total_cost, 4);
            $rows[] = [
                'id' => $p->id,
                'ref' => 'GP-'.str_pad((string) $p->id, 6, '0', STR_PAD_LEFT),
                'date' => $p->date->toDateString(),
                'supplier_name' => $p->supplier_name,
                'quantity_kg' => $p->quantity_kg,
                'unit_price' => $p->unit_price,
                'transport_cost' => $p->transport_cost,
                'total_cost' => $p->total_cost,
                // the accounting voucher exists once the expense has been approved
                'voucher_no' => $p->expense?->voucher_no,
                'status' => $p->expense?->status === 'APPROVED' ? 'APPROVED' : 'PENDING',
                'paid_by' => $p->expense?->treasury_medium,
                'has_invoice' => $this->images->exists($p->invoice_file),
                'running_kg' => $runKg,
                'running_cost' => $runCost,
            ];
        }

        $gasOnly = '0';
        foreach ($purchases as $p) {
            $gasOnly = bcadd($gasOnly, bcmul((string) $p->quantity_kg, (string) $p->unit_price, 4), 4);
        }
        $approved = collect($rows)->where('status', 'APPROVED');
        $approvedCost = bcadd($approved->reduce(fn ($c, $r) => bcadd($c, (string) $r['total_cost'], 4), '0'), '0', 4);

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => [
                'count' => count($rows),
                'quantity_kg' => bcadd($runKg, '0', 4),
                'gas_cost' => bcadd($gasOnly, '0', 4),
                'transport_cost' => bcadd($purchases->reduce(fn ($c, $p) => bcadd($c, (string) $p->transport_cost, 4), '0'), '0', 4),
                'total_cost' => bcadd($runCost, '0', 4),
                // what one kg really cost, delivery included
                'avg_cost_per_kg' => bccomp($runKg, '0', 4) > 0 ? bcdiv($runCost, $runKg, 4) : '0.0000',
                'approved_cost' => $approvedCost,
                'pending_cost' => bcsub($runCost, $approvedCost, 4),
                'with_invoice' => collect($rows)->where('has_invoice', true)->count(),
                'without_invoice' => collect($rows)->where('has_invoice', false)->count(),
            ],
            'by_month' => $this->byMonth($purchases),
            // how much gas has ever been bought, whatever range is on screen
            'overall' => [
                'count' => GasPurchase::count(),
                'quantity_kg' => bcadd((string) (GasPurchase::sum('quantity_kg') ?: 0), '0', 4),
                'total_cost' => bcadd((string) (GasPurchase::sum('total_cost') ?: 0), '0', 4),
            ],
        ];
    }

    /** @return array<int, array{month:string, count:int, quantity_kg:string, total_cost:string}> */
    private function byMonth(Collection $purchases): array
    {
        return $purchases->groupBy(fn ($p) => $p->date->format('Y-m'))->map(function (Collection $group, string $month) {
            return [
                'month' => $month,
                'count' => $group->count(),
                'quantity_kg' => $group->reduce(fn ($c, $p) => bcadd($c, (string) $p->quantity_kg, 4), '0'),
                'total_cost' => $group->reduce(fn ($c, $p) => bcadd($c, (string) $p->total_cost, 4), '0'),
            ];
        })->values()->all();
    }
}
