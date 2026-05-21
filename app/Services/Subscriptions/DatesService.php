<?php

namespace App\Services\Subscriptions;

use App\Models\Billing\Subscription;
use App\Services\Subscriptions\Dates\MonthlyDatesService;
use App\Services\Subscriptions\Dates\YearlyDatesService;
use Carbon\Carbon;
use Carbon\CarbonInterval;

abstract class DatesService
{
    public static function instance(
        Subscription $subscription,
        Carbon $billingAt,
        bool $wantsCurrentUsage
    ): DatesService
    {
        return match($subscription->plan->renew_interval->spec()) {
            CarbonInterval::month()->spec() => new MonthlyDatesService($subscription, $billingAt, $wantsCurrentUsage),
            CarbonInterval::year()->spec() => new YearlyDatesService($subscription, $billingAt, $wantsCurrentUsage),
            default => throw new \Exception('Unsupported invoice interval'),
        };
    }

    public function __construct(
        public Subscription $subscription,
        public Carbon $billingAt,
        public bool $wantsCurrentUsage
    )
    {
    }

    protected function baseDate(): Carbon
    {
        return $this->wantsCurrentUsage
            ? $this->billingAt->copy()
            : $this->computeBaseDate()->copy();
    }

    public function fromDatetime(): ?Carbon
    {
        if (is_null($this->subscription->start_at)) {
            return null;
        }

        return $this->computeFromDate();
    }

    public function toDatetime(): ?Carbon
    {
        if (is_null($this->subscription->start_at)) {
            return null;
        }

        $toDate = $this->computeToDate();

        return $toDate;
//        // todo: check
//        if (!is_null($this->subscription->cancelled_at)) {
//            return $this->subscription->cancelled_at;
//        }
    }

    public function chargesFromDatetime(): ?Carbon
    {
        if (is_null($this->subscription->start_at)) {
            return null;
        }

        $date = $this->computeChargesFromDate();

        return $date;
    }

    public function chargesToDatetime(): ?Carbon
    {
        if (is_null($this->subscription->start_at)) {
            return null;
        }

        $date = $this->computeChargesToDate()->endOfDay();

        return $date;
    }

    abstract protected function computeBaseDate(): Carbon;

    abstract protected function computeToDate(?Carbon $from = null): Carbon;

    abstract protected function computeChargesFromDate(): Carbon;

    abstract protected function computeChargesToDate(): Carbon;

    abstract protected function computeFromDate(): Carbon;
}
