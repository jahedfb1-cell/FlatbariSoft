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
            'mandate_rule' => $this->mandate_rule,
            'holders' => collect([1, 2, 3])->map(fn ($n) => ['name' => $this->{"holder_{$n}_name"}, 'phone' => $this->{"holder_{$n}_phone"}])->all(),
            'sms_phone' => $this->sms_phone,
            'opening_balance' => $this->opening_balance,
            'current_balance' => $this->current_balance,
        ];
    }
}
