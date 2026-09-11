<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bank_name' => $this->bank_name,
            'branch' => $this->branch,
            'account_no' => $this->account_no,
            'account_title' => $this->account_title,
            'opening_balance' => $this->opening_balance,
            'current_balance' => $this->current_balance,
        ];
    }
}
