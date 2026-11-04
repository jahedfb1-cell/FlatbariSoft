<?php

namespace App\Services;

use App\Models\ExpenseHead;
use App\Models\GasPurchase;
use Illuminate\Support\Facades\DB;

/**
 * gas_purchases existed since Phase 1.5 (for GasBillingService's
 * system-loss % calculation) but had no way to actually create one. This
 * fills that gap: recording a purchase also creates the matching Expense
 * (fund_type GAS) so the money side still goes through ExpenseService's
 * Maker-Checker approval and AccountingService — the one real ledger —
 * exactly like every other expense. Nothing here posts to the ledger
 * directly; that only happens when an admin approves the linked Expense.
 */
class GasPurchaseService
{
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly InvoiceImageService $images,
        private readonly SupplierService $suppliers,
    ) {}

    /** $data['invoice_image'] (an UploadedFile) is optional — see InvoiceImageService. */
    public function record(array $data, int $createdBy): GasPurchase
    {
        // Compressed and written first: a bad image fails fast, before any rows exist.
        $stored = isset($data['invoice_image']) ? $this->images->store($data['invoice_image'], 'invoices/gas') : null;

        try {
            return $this->create($data, $createdBy, $stored['path'] ?? null);
        } catch (\Throwable $e) {
            $this->images->delete($stored['path'] ?? null); // don't leave an orphan file behind
            throw $e;
        }
    }

    private function create(array $data, int $createdBy, ?string $invoicePath): GasPurchase
    {
        return DB::transaction(function () use ($data, $createdBy, $invoicePath) {
            $supplierName = $this->suppliers->nameFor($data['supplier_id'] ?? null, $data['supplier_name'] ?? null, 'GAS');
            $data['supplier_name'] = $supplierName;

            // by the cylinder: the total is cylinders x price of one, exactly; the kg and the per-kg rate follow from it
            if (! empty($data['cylinders'])) {
                $data['quantity_kg'] = bcmul((string) $data['cylinders'], (string) $data['cylinder_kg'], 4);
                $data['unit_price'] = bcdiv((string) $data['price_per_cylinder'], (string) $data['cylinder_kg'], 4);
                $goods = bcmul((string) $data['cylinders'], (string) $data['price_per_cylinder'], 4);
            } else {
                $goods = bcmul((string) $data['quantity_kg'], (string) $data['unit_price'], 4);
            }
            $totalCost = bcadd($goods, (string) ($data['transport_cost'] ?? 0), 4);

            $expenseHead = ExpenseHead::where('code', 'GAS_PURCHASE')->firstOrFail();

            $expense = $this->expenses->create([
                'date' => $data['date'],
                'expense_head_id' => $expenseHead->id,
                'payee' => $data['supplier_name'],
                'amount' => $totalCost,
                'description' => ! empty($data['cylinders']) ? "গ্যাস ক্রয় — {$data['cylinders']} সিলিন্ডার × {$data['cylinder_kg']} কেজি" : "গ্যাস ক্রয় — {$data['quantity_kg']} কেজি",
                'treasury_medium' => $data['treasury_medium'],
                'fund_type' => 'GAS',
            ], $createdBy);

            $upgraded = \Illuminate\Support\Facades\Schema::hasColumn('gas_purchases', 'supplier_id');

            return GasPurchase::create([
                'date' => $data['date'],
                'supplier_name' => $data['supplier_name'],
                'quantity_kg' => $data['quantity_kg'],
                'unit_price' => $data['unit_price'],
                'transport_cost' => $data['transport_cost'] ?? 0,
                'total_cost' => $totalCost,
                'invoice_file' => $invoicePath,
                'expense_id' => $expense->id,
            ] + ($upgraded ? [
                'supplier_id' => $data['supplier_id'] ?? null, 'cylinders' => $data['cylinders'] ?? null,
                'cylinder_kg' => $data['cylinder_kg'] ?? null, 'price_per_cylinder' => $data['price_per_cylinder'] ?? null,
            ] : []));
        });
    }
}
