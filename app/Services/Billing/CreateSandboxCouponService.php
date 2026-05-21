<?php

namespace App\Services\Billing;

use App\Billing\BillingService;
use App\Billing\Coupon;
use App\Integration\Connectors\StripeConnector;
use App\Models\Billing\BillingProvider;
use App\Services\BaseService;

/**
 * @method static void dispatch(BillingProvider $billing)
 */
class CreateSandboxCouponService extends BaseService
{
    public function __invoke(BillingProvider $billing)
    {
        if ($billing->service === BillingService::Stripe) {
            (new StripeConnector($billing))->upsertCoupon(Coupon::PLANDALF_SANDBOX_CODE);
        }
    }
}
