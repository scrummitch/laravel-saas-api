<?php

namespace App\Billing\Charges;

use App\Models\Billing\Charge;
use Money\Money;
use Parental\HasParent;

class StandardCharge extends Charge
{
    use HasParent;

    public function calculateAmount(float $quantity = 1.0): Money
    {
        return $this->amount->multiply($quantity);
    }
}
