<?php

namespace App\Billing\Charges;

use App\Models\Billing\Charge;
use Illuminate\Support\Arr;
use Money\Money;
use Parental\HasParent;

class GraduatedCharge extends Charge
{
    use HasParent;

    public function calculateAmount(float $quantity = 1.0): Money
    {
        $total = new Money(0, $this->currency);
        $remainingQuantity = $quantity;
        $lastTierEndQuantity = 0;

        if (empty($this->properties)) {
            throw new \InvalidArgumentException('Graduated charge must have properties');
        }

        $properties = $this->properties;

        // ensure properties are always sorted from smallest to largest
        usort($properties, fn($a, $b) => ($a['up_to'] ?? PHP_FLOAT_MAX) - ($b['up_to'] ?? PHP_FLOAT_MAX));

        foreach ($properties as $tier) {
            $tierQuantity = $this->calculateTierQuantity($remainingQuantity, $tier, $lastTierEndQuantity);

            if ($tierQuantity > 0) {
                $tierTotal = $this->calculateTierTotal($tierQuantity, $tier);
                $total = $total->add($tierTotal);
            }

            $remainingQuantity -= $tierQuantity;
            $lastTierEndQuantity = $tier['up_to'] ?? $lastTierEndQuantity + $tierQuantity;

            if ($remainingQuantity <= 0) break;
        }

        return $total;
    }

    private function calculateTierQuantity(float $remainingQuantity, array $tier, float $lastTierEndQuantity): float
    {
        $tierStart = $lastTierEndQuantity;
        $tierEnd = $tier['up_to'] ?? PHP_FLOAT_MAX;

        return min($remainingQuantity, $tierEnd - $tierStart);
    }

    private function calculateTierTotal(float $tierQuantity, array $tier): Money
    {
        // Dont go over a trillion
        $tierQuantity = max(0, min($tierQuantity, self::MAX_QUANTITY));

        // Dont allow negative charges
        $tierUnitAmount = max(Arr::get($tier, 'unit_amount', 0) ?? 0, 0);

        $unitAmount = new Money($tierUnitAmount, $this->currency);
        $tierTotal = $unitAmount->multiply($tierQuantity);

        if (isset($tier['flat_amount']) && Arr::get($tier, 'flat_amount', 0) !== null) {
            $flatAmount = new Money($tier['flat_amount'], $this->currency);
            $tierTotal = $tierTotal->add($flatAmount);
        }

        return $tierTotal;
    }
}
