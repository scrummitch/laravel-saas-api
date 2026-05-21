<?php

namespace App\Http\Resources\Client;

use App\Billing\ISO4217;
use App\Billing\RenewalCalculator;
use App\Convert\DataObjects\LineItem;
use App\Models\Billing\Charge;
use App\Models\Store\Purchase;
use App\Models\Store\PurchaseItem;
use App\Models\Twin;
use App\Models\Values\PlanType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property Purchase $resource
 * @property \Illuminate\Database\Eloquent\Collection<PurchaseItem> $items
 */
class CheckoutClientResource extends JsonResource
{
    protected Collection $plans;

    public static $wrap = false;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->items
            ->map(function (PurchaseItem $item) {
                return new LineItem(
                    purchasable: $item->purchasable,
                    calculator: new RenewalCalculator(
                        purchasable: $item->purchasable,
                        customer: $this->resource->customer,
                        quantity: $item->quantity,
                    ),
                    cartData: [
                        'quantity' => $item->quantity,
                    ]);
            })
            ->filter();

        $currency = ISO4217::make($this->currency);

        $discount = DB::table('billing_discounts')
            ->where('discountable_type', 'purchase')
            ->where('discountable_id', $this->id)
            ->first();

        $coupon = Twin::query()->find($discount?->coupon_id);

        if ($coupon) {
            $items = $items->each(fn (LineItem $item) => $item->applyCoupon($coupon));
        }

        return [
            'id' => $this->getRouteKey(),
            'object' => 'checkout',

            'current_state' => $this->current_state,
            'summary' => $this->generateSummary($items),

            'line_items' => $items->filter(),
            'billing_start_at' => $this->billing_start_at?->timestamp,

            'discounts' => array_filter([
                value(function () use ($discount, $coupon) {
                    if (! $discount || ! $coupon) {
                        return null;
                    }

                    return [
                        'id' => $discount->key,
                        'object' => 'coupon',
                        'data' => $coupon->data,
                    ];
                }),
            ]),

            'currency' => $currency->getAlpha3(),
            'currency_code' =>  $currency->getAlpha3(),
            'currency_symbol' => $currency->getSymbol(),

            'amount_discount' => $this->getDiscount($items),
            'amount_subtotal' => $this->getTotal($items),
            'amount_total' => $this->getTotal($items),
            'amount_monthly_total' => $this->getMonthlyTotal($items),

            'renew_interval' => $this->renew_interval?->spec(),
            'payment_method' => $this->payment_method?->data,

            'config' => [
                'services' => $this->billing_provider ? [
                    'stripe' => [
                        'publishable_key' => Arr::get($this->billing_provider->config, 'access_token.stripe_publishable_key'),
                    ],
                ] : null,
            ],

            'customer' => new CustomerClientResource($this->customer),
        ];
    }

    private function getDiscount(Collection $lineItems)
    {
        return $lineItems
            ->sum(function (LineItem $lineItem) {
                return $lineItem->toArray()['amount_discount'];
            });
    }

    private function getMonthlyTotal(Collection $lineItems)
    {
        return $lineItems
            ->pluck('plan.charges')
            ->filter()
            ->sum(function (Charge $charge) {
                return $charge->amount->getAmount();
            }) / 12;
    }

    private function getTotal(Collection $lineItems)
    {
        return $lineItems
            ->filter()
            ->sum(function (LineItem $lineItem) {
                $li = $lineItem->toArray();

                return $li['amount_total'];
            });
    }

    private function generateSummary(Collection $lineItems)
    {
        return $this->generateSummaryFromLineItems($lineItems);
    }

    public function generateSummaryFromLineItems($lineItems)
    {
        $li = $lineItems
            ->first(fn (LineItem $item) => $item->purchasable->type === PlanType::standard);

        $addons = $lineItems
            ->where(fn (LineItem $item) => $item->purchasable->type === PlanType::addon);

        $summary = $li
            ? ($li?->purchasable->display_name
                ?? $li->purchasable->package?->name
                ?? $li?->purchasable->name
                ?? 'Payment Plan'
            ) : '';

        if ($addons->isNotEmpty()) {
            $summary .= implode(' ', array_filter([
                $li ? ' with' : null,
                $addons->map(function (LineItem $li) {
                    return $li->purchasable->package?->name ?? $li->purchasable->display_name ?? $li->purchasable->name;
                })->implode(', '),
                Str::plural('add on', $addons->count()),
            ]));
        }

        return $summary;

    }
}
