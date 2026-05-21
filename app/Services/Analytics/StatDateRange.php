<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

enum StatDateRange: string
{
    case today = 'today';
    case yesterday = 'yesterday';
    case last_7_days = 'last_7_days';
    case last_30_days = 'last_30_days';
    case last_365_days = 'last_365_days';
    case this_month = 'this_month';
    case last_month = 'last_month';
    case this_year = 'this_year';
    case last_year = 'last_year';
    case all_time = 'all_time';

    public function isRelative()
    {
        return in_array($this, [
            self::today,
            self::yesterday,
            self::this_month,
            self::last_month,
            self::this_year,
            self::last_year,
        ]);
    }

    public function getDateRange(): CarbonPeriod
    {
        $today = Carbon::today()->timezone('UTC')->toImmutable();

        return match($this) {
            self::today => CarbonPeriod::create(
                $today->copy()->startOfDay(),
                $today->copy()->endOfDay(),
            ),
            self::yesterday => CarbonPeriod::create(
                $today->copy()->subDay()->startOfDay(),
                $today->copy()->subDay()->endOfDay()
            ),
            self::last_7_days => CarbonPeriod::create(
                $today->copy()->subDays(6),
                $today->endOfDay()
            ),
            self::last_30_days => CarbonPeriod::create(
                $today->copy()->subDays(29),
                $today->copy()->endOfDay(),
            ),
            self::last_365_days => CarbonPeriod::create(
                $today->copy()->subDays(364),
                $today
            ),
            self::this_month => CarbonPeriod::create(
                $today->copy()->startOfMonth(),
                $today
            ),
            self::last_month => CarbonPeriod::create(
                $today->copy()->subMonth()->startOfMonth(),
                $today->copy()->subMonth()->endOfMonth()
            ),
            self::this_year => CarbonPeriod::create(
                $today->copy()->startOfYear(),
                $today
            ),
            self::last_year => CarbonPeriod::create(
                $today->copy()->subYear()->startOfYear(),
                $today->copy()->subYear()->endOfYear()
            ),
            self::all_time => CarbonPeriod::create(
                Carbon::parse('2024-01-01'),
                $today
            ),
        };
    }

    public function getComparisonDateRange(): CarbonPeriod
    {
        $range = $this->getDateRange();
        $days = $range->getEndDate()->diffInDays($range->getStartDate());

        return match($this) {
            self::today => CarbonPeriod::create(
                $range->getStartDate()->copy()->subDays(2),
                $range->getStartDate()->copy()->subDays(2)
            ),
            self::yesterday => CarbonPeriod::create(
                $range->getStartDate()->copy()->subDays(2),
                $range->getStartDate()->copy()->subDays(2)
            ),
            self::last_7_days => CarbonPeriod::create(
                $range->getStartDate()->copy()->subDays(7),
                $range->getStartDate()->copy()->subDay()
            ),
            self::last_30_days => CarbonPeriod::create(
                $range->getStartDate()->copy()->subDays(30),
                $range->getStartDate()->copy()->subDay()
            ),
            self::last_365_days => CarbonPeriod::create(
                $range->getStartDate()->copy()->subDays(365),
                $range->getStartDate()->copy()->subDay()
            ),
            self::this_month => CarbonPeriod::create(
                $range->getStartDate()->copy()->subMonthNoOverflow()->startOfMonth(),
                $range->getStartDate()->copy()->subMonthNoOverflow()->endOfMonth()
            ),
            self::last_month => CarbonPeriod::create(
                $range->getStartDate()->copy()->subMonthNoOverflow()->startOfMonth(),
                $range->getStartDate()->copy()->subMonthNoOverflow()->endOfMonth()
            ),
            self::this_year => CarbonPeriod::create(
                $range->getStartDate()->copy()->subYear()->startOfYear(),
                $range->getStartDate()->copy()->subYear()->endOfYear()
            ),
            self::last_year => CarbonPeriod::create(
                $range->getStartDate()->copy()->subYear()->startOfYear(),
                $range->getStartDate()->copy()->subYear()->endOfYear()
            ),
            self::all_time => CarbonPeriod::create(
                Carbon::parse('2000-01-01'),
                $range->getStartDate()->copy()->subDay()
            ),
        };
    }
}
