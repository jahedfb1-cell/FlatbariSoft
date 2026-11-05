<?php

namespace App\Services;

use App\Models\ConstructionMaterialPurchase;
use App\Models\ExpenseHead;
use Illuminate\Support\Facades\DB;

/**
 * Owner-requested (2026-09): a small purchase-history module for
 * construction materials — item, quantity, unit, rate, supplier — not a
 * stock/inventory system (no remaining-quantity or consumption tracking,
 * unlike the sibling WBL project's fabric batches). Every purchase is
 * also an Expense (fund_type CAPITAL) so the money side still goes
 * through the one real ledger via ExpenseService's Maker-Checker
 * approval, same as GasPurchaseService.
 */
class ConstructionMaterialPurchaseService
{
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly InvoiceImageService $images,
        private readonly SupplierService $suppliers,
    ) {}

    /** $data['invoice_image'] (an UploadedFile) is optional — see InvoiceImageService. */
    public function record(array $data, int $createdBy): ConstructionMaterialPurchase
    {
        $stored = isset($data['invoice_image']) ? $this->images->store($data['invoice_image'], 'invoices/materials') : null;

        try {
            return $this->create($data, $createdBy, $stored['path'] ?? null);
        } catch (\Throwable $e) {
            $this->images->delete($stored['path'] ?? null);
            throw $e;
        }
    }

    private function create(array $data, int $createdBy, ?string $invoicePath): ConstructionMaterialPurchase
    {
        return DB::transaction(function () use ($data, $createdBy, $invoicePath) {
            [$supplierName, $totalCost] = $this->figures($data);

            $expenseHead = ExpenseHead::where('code', 'CONSTRUCTION_MATERIALS')->firstOrFail();

            $expense = $this->expenses->create([
                'date' => $data['date'],
                'expense_head_id' => $expenseHead->id,
                'payee' => $supplierName ?? $data['item_name'],
                'amount' => $totalCost,
                'description' => "নির্মাণ সামগ্রী — {$data['item_name']} ({$data['quantity']} {$data['unit']})",
                'treasury_medium' => $data['treasury_medium'],
                'fund_type' => 'CAPITAL',
            ], $createdBy);

            return ConstructionMaterialPurchase::create([
                'date' => $data['date'],
                'item_name' => $data['item_name'],
                'quantity' => $data['quantity'],
                'unit' => $data['unit'],
                'unit_rate' => $data['unit_rate'],
                'supplier_name' => $supplierName,
                'transport_cost' => $data['transport_cost'] ?? 0,
                'total_cost' => $totalCost,
                'invoice_file' => $invoicePath,
                'expense_id' => $expense->id,
                'created_by' => $createdBy,
            ] + (\Illuminate\Support\Facades\Schema::hasColumn('construction_material_purchases', 'supplier_id') ? ['supplier_id' => $data['supplier_id'] ?? null] : []));
        });
    }

    /** @return array{0: ?string, 1: string} the supplier's name and the total, worked out on the server */
    private function figures(array $data): array
    {
        $name = $this->suppliers->nameFor($data['supplier_id'] ?? null, $data['supplier_name'] ?? null, 'MATERIALS');
        $total = bcadd(bcmul((string) $data['quantity'], (string) $data['unit_rate'], 4), (string) ($data['transport_cost'] ?? 0), 4);

        return [$name, $total];
    }

    private function mustBeOpen(ConstructionMaterialPurchase $purchase): void
    {
        if ($purchase->expense?->status !== 'PENDING') {
            throw \Illuminate\Validation\ValidationException::withMessages(['purchase' => 'এই ক্রয়ের খরচ অনুমোদিত হয়ে গেছে — আর বদলানো বা বাতিল করা যাবে না।']);
        }
    }

    /** Corrects a purchase whose expense is still pending, and that expense with it. $data['invoice_image'] replaces the bill photo. */
    public function update(ConstructionMaterialPurchase $purchase, array $data): ConstructionMaterialPurchase
    {
        $purchase->loadMissing('expense');
        $this->mustBeOpen($purchase);
        $stored = isset($data['invoice_image']) ? $this->images->store($data['invoice_image'], 'invoices/materials') : null;

        try {
            $old = $purchase->invoice_file;
            $purchase = DB::transaction(function () use ($purchase, $data, $stored) {
                [$supplierName, $totalCost] = $this->figures($data);
                $purchase->expense->update([
                    'date' => $data['date'], 'payee' => $supplierName ?? $data['item_name'], 'amount' => $totalCost,
                    'description' => "নির্মাণ সামগ্রী — {$data['item_name']} ({$data['quantity']} {$data['unit']})",
                    'treasury_medium' => $data['treasury_medium'],
                ]);
                $purchase->update([
                    'date' => $data['date'], 'item_name' => $data['item_name'], 'quantity' => $data['quantity'], 'unit' => $data['unit'],
                    'unit_rate' => $data['unit_rate'], 'supplier_name' => $supplierName, 'transport_cost' => $data['transport_cost'] ?? 0,
                    'total_cost' => $totalCost,
                ] + ($stored ? ['invoice_file' => $stored['path']] : [])
                  + (\Illuminate\Support\Facades\Schema::hasColumn('construction_material_purchases', 'supplier_id') ? ['supplier_id' => $data['supplier_id'] ?? null] : []));

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
    public function cancel(ConstructionMaterialPurchase $purchase): void
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
