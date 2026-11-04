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
            [$data, $totalCost] = $this->figures($data);

            $expenseHead = ExpenseHead::where('code', 'GAS_PURCHASE')->firstOrFail();

            $expense = $this->expenses->create([
                'date' => $data['date'],
                'expense_head_id' => $expenseHead->id,
                'payee' => $data['supplier_name'],
                'amount' => $totalCost,
                'description' => $this->description($data),
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

    /**
     * The supplier's name and the money, worked out on the server. By the cylinder the total is cylinders x the price of one,
     * exactly; the kg and the per-kg rate follow from it.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function figures(array $data): array
    {
        $data['supplier_name'] = $this->suppliers->nameFor($data['supplier_id'] ?? null, $data['supplier_name'] ?? null, 'GAS');
        if (! empty($data['cylinders'])) {
            $data['quantity_kg'] = bcmul((string) $data['cylinders'], (string) $data['cylinder_kg'], 4);
            $data['unit_price'] = bcdiv((string) $data['price_per_cylinder'], (string) $data['cylinder_kg'], 4);
            $goods = bcmul((string) $data['cylinders'], (string) $data['price_per_cylinder'], 4);
        } else {
            $data['cylinders'] = $data['cylinder_kg'] = $data['price_per_cylinder'] = null;
            $goods = bcmul((string) $data['quantity_kg'], (string) $data['unit_price'], 4);
        }

        return [$data, bcadd($goods, (string) ($data['transport_cost'] ?? 0), 4)];
    }

    private function description(array $data): string
    {
        return ! empty($data['cylinders']) ? "গ্যাস ক্রয় — {$data['cylinders']} সিলিন্ডার × {$data['cylinder_kg']} কেজি" : "গ্যাস ক্রয় — {$data['quantity_kg']} কেজি";
    }

    /** A purchase whose expense is still waiting for approval can still be corrected; once approved the money is in the books. */
    private function mustBeOpen(GasPurchase $purchase): void
    {
        if ($purchase->expense?->status !== 'PENDING') {
            throw \Illuminate\Validation\ValidationException::withMessages(['purchase' => 'এই ক্রয়ের খরচ অনুমোদিত হয়ে গেছে — আর বদলানো বা বাতিল করা যাবে না।']);
        }
    }

    /** Corrects a pending purchase, and the expense that waits for approval with it. $data['invoice_image'] replaces the bill photo. */
    public function update(GasPurchase $purchase, array $data): GasPurchase
    {
        $purchase->loadMissing('expense');
        $this->mustBeOpen($purchase);
        $stored = isset($data['invoice_image']) ? $this->images->store($data['invoice_image'], 'invoices/gas') : null;

        try {
            $old = $purchase->invoice_file;
            $purchase = DB::transaction(function () use ($purchase, $data, $stored) {
                [$data, $totalCost] = $this->figures($data);
                $purchase->expense->update([
                    'date' => $data['date'], 'payee' => $data['supplier_name'], 'amount' => $totalCost,
                    'description' => $this->description($data), 'treasury_medium' => $data['treasury_medium'],
                ]);
                $upgraded = \Illuminate\Support\Facades\Schema::hasColumn('gas_purchases', 'supplier_id');
                $purchase->update([
                    'date' => $data['date'], 'supplier_name' => $data['supplier_name'], 'quantity_kg' => $data['quantity_kg'], 'unit_price' => $data['unit_price'],
                    'transport_cost' => $data['transport_cost'] ?? 0, 'total_cost' => $totalCost,
                ] + ($stored ? ['invoice_file' => $stored['path']] : []) + ($upgraded ? [
                    'supplier_id' => $data['supplier_id'] ?? null, 'cylinders' => $data['cylinders'], 'cylinder_kg' => $data['cylinder_kg'], 'price_per_cylinder' => $data['price_per_cylinder'],
                ] : []));

                return $purchase->fresh('expense');
            });
            if ($stored) {
                $this->images->delete($old);
            }

            return $purchase;
        } catch (\Throwable $e) {
            $this->images->delete($stored['path'] ?? null);
            throw $e;
        }
    }

    /** Takes back a purchase entered by mistake - only while its expense has not been approved. */
    public function cancel(GasPurchase $purchase): void
    {
        $purchase->loadMissing('expense');
        $this->mustBeOpen($purchase);

        DB::transaction(function () use ($purchase) {
            $expense = $purchase->expense;
            $purchase->delete();
            $expense->delete();
        });
        $this->images->delete($purchase->invoice_file);
    }
}
