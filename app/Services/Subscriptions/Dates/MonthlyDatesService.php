<?php

namespace App\Services\Subscriptions\Dates;

use App\Services\Subscriptions\DatesService;
use Carbon\Carbon;
use Override;

class MonthlyDatesService extends DatesService
{

    public function computeFromDate(?Carbon $date = null): Carbon
    {
        $date ??= $this->baseDate();

        return $this->previousAnniversaryDay($date);

//        if ($this->isTerminated()) {
//            // return some other shit
//        }
//
//        if ($plan->pay_in_arrears) {
//
//        }
//
//        if ($plan->billing_anchor === 'calendar') {
//            return $this->baseDate->beginningOfMonth();
//        }
//
//        return $this->previousAnniversaryDay($this->baseDate);
    }

    public function previousAnniversaryDay(Carbon $date): Carbon
    {
        if ($this->subscription->plan->billing_anchor === 'anniversary'
            && $this->isLastDayOfMonth($date)
            && $date->day < $this->subscription->start_at->day
        ) {
            $day = $date->day;
        } else {
            $day = $this->subscription->start_at->day;
        }

        if ($date->day < $day) {
            $year = ($date->month == 1) ? $date->year - 1 : $date->year;
            $month = ($date->month == 1) ? 12 : $date->month - 1;
        } else {
            $year = $date->year;
            $month = $date->month;
        }

        return Carbon::create($year, $month, $day);
    }

    protected function isLastDayOfMonth(Carbon $date): bool
    {
        return $date->copy()->addDay()->month !== $date->month;
    }


    #[Override]
    protected function computeToDate(Carbon $from = null): Carbon
    {
        $from ??= $this->computeFromDate();

        if ($this->subscription->plan->billing_anchor === 'calendar'
            || $this->subscription->start_at->day === 1
        ) {
            return $from->clone()->endOfMonth();
        }


        $year = $from->year;
        $month = $from->month + 1;
        $day = $this->subscription->start_at->day - 1;

        if ($month > 12) {
            $month = 1;
            $year += 1;
        }

        $date = Carbon::create($year, $month, $day);

        if ($this->isLastDayOfMonth($date) && $this->subscription->start_at->day > $date->day) {
            return $date->clone()->subDay();
        }

        return $date;
    }

    #[Override]
    protected function computeChargesFromDate(): Carbon
    {
        return $this->previousAnniversaryDay($this->baseDate());
    }

    #[Override]
    protected function computeBaseDate(): Carbon
    {
        if ($this->subscription->plan->billing_anchor === 'anniversary'
            && $this->isLastDayOfMonth($this->billingAt)
            && $this->billingAt->day < $this->subscription->start_at->day
        ) {
            $previousMonth = $this->billingAt->copy()->subMonth();

            if ($previousMonth->copy()->endOfMonth()->day >= $this->subscription->start_at->day) {
                return $previousMonth->setDay($this->subscription->start_at->day);
            }

            return $previousMonth->endOfMonth();
        }

        return $this->billingAt->copy()->subMonth();
    }


    #[Override]
    protected function computeChargesToDate(): Carbon
    {
        if ($this->subscription->plan->billing_anchor === 'calendar') {
            return $this->computeBaseDate()->endOfMonth();
        }

        return $this->computeToDate($this->computeChargesFromDate());
    }
}
