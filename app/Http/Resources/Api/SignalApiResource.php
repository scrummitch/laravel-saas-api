<?php

namespace App\Http\Resources\Api;

use App\Billing\ChargeFormatter;
use App\Models\Account\Customer;
use App\Models\Account\SignalDependency;
use App\Models\Account\SignalID;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Money\Currency;
use Money\Money;

/**
 * @property SignalID $type
 * @property Customer $customer
 * @property Money|null $amount
 * @property string|null $amount_raw
 * @property Currency $currency
 * @property array|null $metadata
 * @property Carbon|null $effective_at
 * @property Carbon $created_at
 * @property Collection<SignalDependency> $dependencies
 */
class SignalApiResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type->name,
            'category' => $this->type->getCategory(),
            'customer' => $this->when($this->customer, function () {
                return [
                    'id' => $this->customer->getRouteKey(),
                ];
            }, null),
            'amount' => ChargeFormatter::format($this->amount),
            'amount_raw' => $this->amount_raw,
            'metadata' => $this->metadata,
            'effective_at' => $this->effective_at,
            'created_at' => $this->created_at,
        ];
    }
}
