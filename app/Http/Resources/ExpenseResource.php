<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'expense_head_id' => $this->expense_head_id,
            'payee' => $this->payee,
            'amount' => $this->amount,
            'treasury_medium' => $this->treasury_medium,
            'fund_type' => $this->fund_type,
            'resolution_id' => $this->resolution_id,
            'work_budget_id' => $this->work_budget_id,
            'voucher_no' => $this->voucher_no,
            'status' => $this->status,
            'description' => $this->description,
            'attachments_count' => $this->attachments_count ?? (Schema::hasTable('expense_attachments') ? $this->attachments()->count() : 0),
            'approvals_count' => $this->approvals()->count(),
            // can still be corrected: nobody has approved it and it is not the other half of a purchase / salary / bill
            'editable' => app(\App\Services\ExpenseService::class)->isEditable($this->resource),
        ];
    }
}
