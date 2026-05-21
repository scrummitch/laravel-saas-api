<?php

namespace App\Billing;

use App\Models\Account\Customer;
use App\Models\Billing\Charge;
use App\Models\Pricing\Plan;
use App\Models\Usage\AggregationValue;
use App\Models\Usage\Metric;
use App\Models\Usage\UsageEvent;
use App\Store\IsPurchasable;
use Carbon\CarbonInterval;
use Illuminate\Support\Arr;
use Money\Money;

class RenewalCalculator
{
    public function __construct(
        public IsPurchasable $purchasable,
        public ?Customer     $customer = null,
        protected float|null $quantity = null,
    ) {
    }

    public function calculate(): Money
    {
        if ($this->quantity < 0) {
            return new Money(0, $this->purchasable->currency);
        }

        return match(true) {
            $this->purchasable instanceof Plan => $this->calculatePlan(),
            $this->purchasable instanceof Charge => $this->calculateCharge(),
            default => new Money(0, $this->purchasable->currency),
        };
    }

    private function calculateCharge(): Money
    {
        return $this->purchasable->calculateAmount($this->quantity);
    }

    protected function calculatePlan(): Money
    {
        $totalAmount = new Money(0, $this->purchasable->currency);

        $this->purchasable->loadMissing(['inclusions' => function ($query) {
            // only query where it has a charge thats mode=in_advance
            $query->whereHas('charge', function ($query) {
                $query->where('mode', 'in_advance');
            });
        }, 'inclusions.charge', 'inclusions.metric']);

        foreach ($this->purchasable->inclusions as $inclusion) {
            if ($inclusion->charge) {
                $quantity = $this->determineQuantity($inclusion->charge, $inclusion->metric);
                $chargeAmount = $inclusion->charge->calculateAmount($quantity);
                $totalAmount = $totalAmount->add($chargeAmount);
            }
        }

        return $totalAmount;
    }

    private function determineQuantity(Charge $charge, ?Metric $metric): int
    {
        if (!is_null($this->quantity)) {
            return $this->quantity;
        }

        if ($metric) {
            return $this->lookupMetricUsage($metric);
        }

        return $charge->properties['quantity'] ?? 1;
    }

    private function lookupMetricUsage(Metric $metric): int
    {
        if ($metric->aggregation === AggregationValue::Latest) {
            $a = UsageEvent::query()
                ->where('customer_id', $this->customer->id)
                ->where('event_name', $metric->event_name)
                ->latest()
                ->select('properties->quantity as quantity')
                ->first();

            return Arr::get($a, 'quantity', 0);
        }

        return $this->quantity ?? 1;
    }

    public function comparativeMonthlyPrice(): Money
    {
        $totalAmount = $this->calculate();

        if ($this->purchasable->renew_interval->compare(CarbonInterval::month()) === 0) {
            return $totalAmount;
        }

        return $totalAmount->divide($this->purchasable->renew_interval->totalMonths ?? 12);
    }

    public function setQuantity(float $quantity): void
    {
        $this->quantity = $quantity;
    }

}
