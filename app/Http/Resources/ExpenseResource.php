<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'voucher_no' => $this->voucher_no,
            'status' => $this->status,
            'approvals_count' => $this->approvals()->count(),
        ];
    }
}
