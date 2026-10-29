<?php

namespace App\Services;

use App\Models\ConstructionMaterialPurchase;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\GasPurchase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Everything an approver needs in front of them before saying yes: what the money is for, who asked, the
 * papers behind it (files uploaded to the expense itself, and the bill photo of a gas / construction-material
 * purchase that created it), and what earlier approvers wrote. Also stores those uploaded papers.
 */
class ExpenseDetailService
{
    public function __construct(private readonly ExpenseService $expenses, private readonly InvoiceImageService $images) {}

    /** @return array<string, mixed> */
    public function present(Expense $expense): array
    {
        $expense->loadMissing('expenseHead', 'creator', 'attachments', 'approvals.approver');

        return [
            'id' => $expense->id,
            'date' => $expense->date?->toDateString(),
            'head_bn' => $expense->expenseHead?->name_bn,
            'head_en' => $expense->expenseHead?->name_en,
            'payee' => $expense->payee,
            'amount' => (string) $expense->amount,
            'description' => $expense->description,
            'treasury_medium' => $expense->treasury_medium,
            'fund_type' => $expense->fund_type,
            'resolution_id' => $expense->resolution_id,
            'voucher_no' => $expense->voucher_no,
            'status' => $expense->status,
            'created_by' => $expense->creator?->displayName(),
            'created_at' => $expense->created_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i'),
            'approvals_needed' => $this->expenses->requiredApprovals($expense),
            'approvals' => $expense->approvals->map(fn ($a) => [
                'by' => $a->approver?->displayName(),
                'at' => $a->approved_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i'),
                'note' => $a->note,
            ])->values(),
            'attachments' => $expense->attachments->map(fn ($f) => [
                'id' => $f->id, 'name' => $f->original_name, 'mime' => $f->mime, 'size' => $f->size,
            ])->values(),
            'source' => $this->source($expense),
        ];
    }

    /** The purchase this expense was raised from, if any, with whether it has a bill photo to look at. */
    private function source(Expense $expense): ?array
    {
        $gas = GasPurchase::where('expense_id', $expense->id)->first();
        if ($gas) {
            return ['type' => 'gas', 'purchase_id' => $gas->id, 'has_invoice' => $this->images->exists($gas->invoice_file)];
        }
        $mat = ConstructionMaterialPurchase::where('expense_id', $expense->id)->first();
        if ($mat) {
            return ['type' => 'materials', 'purchase_id' => $mat->id, 'has_invoice' => $this->images->exists($mat->invoice_file), 'item' => $mat->item_name];
        }

        return null;
    }

    /** @param  list<UploadedFile>  $files */
    public function attach(Expense $expense, array $files, int $by): void
    {
        foreach ($files as $file) {
            $expense->attachments()->create([
                'path' => $file->store('expenses/'.$expense->id, 'local'),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => (int) $file->getSize(),
                'uploaded_by' => $by,
            ]);
        }
    }

    public function remove(ExpenseAttachment $attachment): void
    {
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();
    }
}
