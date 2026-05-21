<?php

namespace App\Billing\Charges;

use App\Models\Billing\Charge;
use Money\Money;

class OneTimeCharge extends Charge
{
    public function calculateAmount(float $quantity = 1.0): Money
    {
        return $this->amount->multiply($quantity);
    }
}
