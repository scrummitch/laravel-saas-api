<?php

namespace App\Billing\Charges;

use App\Models\Billing\Charge;
use Money\Money;
use Parental\HasParent;

class PackageCharge extends Charge
{
    use HasParent;

    public function calculateAmount(float $quantity = 1.0): Money
    {
        $freeUnits = $this->properties['free_units'] ?? 0;
        $packageSize = $this->properties['package_size'] ?? 1;
        $packageAmount = new Money($this->properties['amount'], $this->currency);

        // If quantity is less than or equal to free units, no charge
        if ($quantity <= $freeUnits) {
            return new Money(0, $this->currency);
        }

        // Calculate chargeable quantity
        $chargeableQuantity = $quantity - $freeUnits;

        // Calculate number of packages
        $packages = ceil($chargeableQuantity / $packageSize);

        return $packageAmount->multiply($packages);
    }
}
