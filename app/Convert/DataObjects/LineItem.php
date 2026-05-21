<?php

namespace App\Convert\DataObjects;

use App\Billing\RenewalCalculator;
use App\Models\Twin;
use App\Store\IsPurchasable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Money\Money;

class LineItem implements Arrayable
{
    public $coupon = null;

    protected array $properties;

    protected int $quantity;

    protected ?int $minQuantity;

    protected ?int $maxQuantity;

    protected $_toArray = null;

    public function __construct(
        public IsPurchasable     $purchasable,
        public RenewalCalculator $calculator,
        public array             $cartData = [],
    ) {
        $this->properties  = $cartData['properties'] ?? [];
        $this->quantity    = $cartData['quantity'] ?? 1;
        $this->minQuantity = $cartData['min_quantity'] ?? null;
        $this->maxQuantity = $cartData['max_quantity'] ?? null;
    }

    public function toArray(): array
    {
        return $this->_toArray ??= $this->buildArray();
    }

    private function calculateMonthlyEquivalent(Money $monthlyEquivalent): string
    {
        return $this->applyDiscount($monthlyEquivalent)->getAmount();
    }

    public function applyCoupon(Twin $coupon)
    {
        $this->coupon = $coupon;
    }

    private function applyDiscount(Money $amount): Money
    {
        if ($this->coupon) {
            $coupon = $this->coupon->object();

            if ($coupon->percent_off) {
                $amount = $amount->subtract($this->calculateDiscount($amount));
            }
        }

        return $amount;
    }

    private function calculateDiscount(Money $amount): Money
    {
        return $amount->multiply($this->coupon?->object()->percent_off / 100);
    }

    private function buildArray()
    {
        $this->calculator->setQuantity(Arr::get($this->cartData, 'quantity', 1));
        $subTotal = $this->calculator->calculate();
        $families = $this->purchasable->getProductFamilies();

        $result = [
            'id' => $this->purchasable->getPurchasableId(),
            'object' => $this->purchasable->getType(),

            'display_name' => $this->purchasable->getDisplayName(),
            'description' => $this->purchasable->getDescription(),

            'quantity' => [
                'value' => $this->quantity,
                'min' => $this->minQuantity,
                'max' => $this->maxQuantity,
                'increment' => 1,
            ],

            'properties' => $this->properties,
            'currency_code' => $this->purchasable->currency->getCode(),

            'amount_discount' => $this->calculateDiscount($subTotal)?->getAmount(),
            'amount_subtotal' => $subTotal->getAmount(),
            'amount_tax' => (new Money(0, $this->purchasable->getCurrency()))->getAmount(),
            'amount_total' => $this->applyDiscount($subTotal)->getAmount(),
        ];

        if ($this->purchasable->isRecurring()) {
            $result['plan'] = [
                'id' => $this->purchasable->id,
                'type' => $this->purchasable->type,
                'object' => 'plan',
                'products' => [
                    [
                        // todo
                        //                    'id' => $this->purchasable->getPurchasableId(),
                        //                    'family' => $families?->first()?->getRouteKey(),
                    ]
                ],
                // deprecated
                'product_family' => $families?->first()?->getRouteKey(),
            ];
            $result['amount_monthly_total'] = $this->calculateMonthlyEquivalent($this->calculator->comparativeMonthlyPrice());
            $result['renew_interval'] = $this->purchasable->getRenewInterval();
            $result['invoice_interval'] = $this->purchasable->getInvoiceInterval();
        }

        return $result;
    }
}
