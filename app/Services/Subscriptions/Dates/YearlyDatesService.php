<?php

namespace App\Services\Subscriptions\Dates;

use App\Services\Subscriptions\DatesService;
use Carbon\Carbon;
use Override;

class YearlyDatesService extends DatesService
{

    public function computeFromDate(): Carbon
    {
        $baseDate = $this->baseDate();

        return $this->subscription->plan->billing_anchor === 'anniversary'
            ? $this->previousAnniversaryDay($baseDate)
            : $baseDate->startOfYear();
    }

    #[Override]
    public function computeChargesFromDate(): Carbon
    {
        if ($this->subscription->plan->invoice_interval->spec() === 'P1M') {
            // yeet over to montly
            return (new MonthlyDatesService(
                $this->subscription,
                $this->billingAt,
                $this->wantsCurrentUsage
            ))->computeChargesFromDate();
        }

        $anniversaryDay = $this->subscription->start_at->day;
        $currentMonth = $this->billingAt->month;
        $currentDay = $this->billingAt->day;
        $currentYear = $this->billingAt->year;

        // If we're past the anniversary day this month, return this month's anniversary day
        if ($currentDay >= $anniversaryDay) {
            return Carbon::create($currentYear, $currentMonth, $anniversaryDay);
        }

        // Otherwise return previous month's anniversary day
        return Carbon::create($currentYear, $currentMonth - 1, $anniversaryDay);
    }

    public function computeChargesToDate(): Carbon
    {
        if ($this->subscription->plan->invoice_interval->spec() === 'P1M') {
            // yeet over to montly
            return (new MonthlyDatesService(
                $this->subscription,
                $this->billingAt->copy(),
                $this->wantsCurrentUsage
            ))->computeChargesToDate();
        }

        $anniversaryDay = $this->subscription->start_at->day;
        $currentMonth = $this->billingAt->month;
        $currentDay = $this->billingAt->day;
        $currentYear = $this->billingAt->year;

        // If we're past the anniversary day this month, return next month's day before anniversary
        if ($currentDay >= $anniversaryDay) {
            $nextMonth = $currentMonth + 1;
            $nextYear = $currentYear;

            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear += 1;
            }

            return Carbon::create($nextYear, $nextMonth, $anniversaryDay - 1);
        }

        // Otherwise return this month's day before anniversary
        return Carbon::create($currentYear, $currentMonth, $anniversaryDay - 1);
    }

    #[Override]
    protected function computeBaseDate(): Carbon
    {
        return $this->billingAt->copy()->subYear();
    }

    private function previousAnniversaryDay(Carbon $baseDate): Carbon
    {
        $year = $this->previousStartedInLastYear($baseDate)
            ? $baseDate->year - 1
            : $baseDate->year;
        $month = $this->subscription->start_at->month;
        $day = $this->subscription->start_at->day;

        return Carbon::create($year, $month, $day);
    }

    private function previousStartedInLastYear(Carbon $date): bool
    {
        if ($date->month < $this->subscription->start_at->month) {
            return true;
        }

        if ($date->month === $this->subscription->start_at->month && $date->day < $this->subscription->start_at->day) {
            return true;
        }

        return false;
    }

    #[Override]
    protected function computeToDate(?Carbon $fromDate = null): Carbon
    {
        $fromDate = $fromDate ?? $this->computeFromDate();

        $year = $fromDate->year + 1;
        $month = $fromDate->month;
        // todo: seriously check this is correct
        $day = $this->subscription->start_at->copy()->subDay()->day;

        return Carbon::create($year, $month, $day);
    }
}
