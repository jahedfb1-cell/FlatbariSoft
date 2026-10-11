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
            $totalCost = bcadd(
                bcmul((string) $data['quantity'], (string) $data['unit_rate'], 4),
                (string) ($data['transport_cost'] ?? 0),
                4,
            );

            $expenseHead = ExpenseHead::where('code', 'CONSTRUCTION_MATERIALS')->firstOrFail();

            $expense = $this->expenses->create([
                'date' => $data['date'],
                'expense_head_id' => $expenseHead->id,
                'payee' => $data['supplier_name'] ?? $data['item_name'],
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
                'supplier_name' => $data['supplier_name'] ?? null,
                'transport_cost' => $data['transport_cost'] ?? 0,
                'total_cost' => $totalCost,
                'invoice_file' => $invoicePath,
                'expense_id' => $expense->id,
                'created_by' => $createdBy,
            ]);
        });
    }
}
