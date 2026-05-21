<?php

namespace App\Models\Convert;

use App\Billing\Coupon;
use App\Billing\CurrencyCast;
use App\Database\Model;
use App\Models\Account\Customer;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Subscription;
use App\Models\Twin;
use App\Models\Values\PlanType;
use Carbon\Carbon;
use Illuminate\Support\Arr;

/**
 * @deprecated
 */
class Checkout extends Model
{
    protected $table = 'convert_checkouts';

    protected $casts = [
        'current_state' => CheckoutState::class,
        'line_items' => 'array',
        'config' => 'json',
        'currency' => CurrencyCast::class,
    ];




}
