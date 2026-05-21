<?php

namespace App\Http\Resources\Client;

use App\Billing\ChargeFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Money\Money;

/**
 * @property Money $amount
 */
class ChargeClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,

            'amount_raw' => $this->amount->getAmount(),
            'currency' => $this->amount->getCurrency()->getCode(),
            'amount_formatted' => ChargeFormatter::format($this->amount),
        ];
    }
}
