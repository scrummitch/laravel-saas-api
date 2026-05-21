<?php

namespace App\Services\Analytics;

use App\Billing\ChargeFormatter;
use App\Models\Client;
use ArrayAccess;
use Carbon\CarbonPeriod;
use Closure;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Money\Currency;
use Money\Money;

class DashboardStatsService
{
    protected DateTimeZone $timezone;
    protected ?Closure $filter = null;
    private ?Closure $output = null;

    public function __construct(
        protected StatDateRange $range,
        protected Client $client,
        ?string $timezone = null
    )
    {
        try {
            $this->timezone = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            $this->timezone = new \DateTimeZone('UTC');
        }
    }

    public function filter(\Closure $filterer): self
    {
        $this->filter = $filterer;

        return $this;
    }

    public function output(\Closure $formatter): self
    {
        $this->output = $formatter;

        return $this;
    }

    public function getGlobalStats(): ArrayAccess|array|Collection
    {
        $currentRange = $this->range->getDateRange()->shiftTimezone($this->timezone);
        $previousRange = $this->range->getComparisonDateRange()->shiftTimezone($this->timezone);

        $getMetrics = function (CarbonPeriod $dateRange) {
            $attributionsSubquery = DB::table('convert_attributions')
                ->select('activity_id', DB::raw('SUM(CAST(proceeds_amount_gross AS DECIMAL(20,2))) AS total_revenue'))
                ->whereNotNull('proceeds_amount_gross')
                ->groupBy('activity_id');

            $query = DB::table('intel_activities as a')
                ->select([
                    DB::raw('COUNT(DISTINCT a.collector_id) as unique_views'),
                    DB::raw('COUNT(a.collector_id) as views'),
                    DB::raw('SUM(a.has_started) as starts'),
                    DB::raw('SUM(a.has_completed) as conversions'),
//                    DB::raw('COUNT(CASE WHEN a.has_completed = 1 THEN a.id END) as conversions'),
                    DB::raw('SUM(a.has_completed) as conversions'),
                    DB::raw('COUNT(DISTINCT CASE WHEN a.has_completed = 1 THEN a.id END) AS conversions'),
                    DB::raw('SUM(CASE WHEN a.has_entered = 1 AND a.has_started = 0 THEN 1 ELSE 0 END) as bounces'),
                    DB::raw('COALESCE(SUM(CAST(attr.total_revenue as DECIMAL(20,2))), 0) as revenue'),
                    DB::raw('\'USD\' as currency'),
                ])
                ->join('intel_scenarios as s', 's.id', '=', 'a.scenario_id')
//                ->leftJoin('convert_attributions as attr', function($join) {
//                    $join->on('attr.activity_id', '=', 'a.id')->whereNotNull('attr.proceeds_amount_gross');
//                })
                ->leftJoinSub($attributionsSubquery, 'attr', function ($join) {
                    $join->on('attr.activity_id', '=', 'a.id');
                })
                ->where('a.client_id', $this->client->id)
                ->whereBetween('a.created_at', [
                    $dateRange->start->setTimezone('UTC'),
                    $dateRange->end->setTimezone('UTC')
                ])
                ->when($this->filter, function($query) {
                    return call_user_func($this->filter, $query);
                });

            logger()->info(logname('QUERY'), [
                'sql' => $query->toRawSql(),
                'url' => request()->url(),
            ]);

            return $query->get();
        };

        // Fetch metrics for both periods
        $currentPeriod = $getMetrics($currentRange);
        $previousPeriod = $getMetrics($previousRange);

        if ($this->output instanceof \Closure) {
            return call_user_func($this->output, $currentPeriod, $previousPeriod);
        }

        return $this->defaultOutput($currentPeriod, $previousPeriod);
    }

    public function getScenarioStats()
    {
        $currentRange = $this->range->getDateRange()->shiftTimezone($this->timezone);
        $previousRange = $this->range->getComparisonDateRange()->shiftTimezone($this->timezone);

        $getMetrics = function (CarbonPeriod $dateRange) {
            $attributionsSubquery = DB::table('convert_attributions')
                ->select('activity_id', DB::raw('SUM(CAST(proceeds_amount_gross AS DECIMAL(20,2))) AS total_revenue'))
                ->whereNotNull('proceeds_amount_gross')
                ->groupBy('activity_id');

            $query = DB::table('intel_activities as a')
                ->select([
                    DB::raw('COUNT(DISTINCT a.collector_id) as unique_views'),
                    DB::raw('COUNT(a.collector_id) as views'),
                    DB::raw('SUM(a.has_started) as starts'),
                    DB::raw('SUM(a.has_completed) as conversions'),
//                    DB::raw('COUNT(DISTINCT CASE WHEN a.has_completed = 1 THEN a.id END) AS conversions'),
                    DB::raw('COUNT(CASE WHEN a.has_completed = 1 THEN a.id END) as conversions'),
                    DB::raw('SUM(CASE WHEN a.has_entered = 1 AND a.has_started = 0 THEN 1 ELSE 0 END) as bounces'),
                    DB::raw('COALESCE(SUM(CAST(attr.total_revenue as DECIMAL(20,2))), 0) as revenue'),
                    DB::raw('\'USD\' as currency'),
//                    DB::raw('s.id as scenario_id'),
                ])
                ->join('intel_scenarios as s', 's.id', '=', 'a.scenario_id')
//                ->leftJoin('convert_attributions as attr', function($join) {
//                    $join
//                        ->on('attr.activity_id', '=', 'a.id')
//                        ->whereNotNull('attr.proceeds_amount_gross');
//                })
                ->leftJoinSub($attributionsSubquery, 'attr', function ($join) {
                    $join->on('attr.activity_id', '=', 'a.id');
                })
                ->where('a.client_id', $this->client->id)
                ->whereBetween('a.created_at', [
                    $dateRange->start->setTimezone('UTC'),
                    $dateRange->end->setTimezone('UTC')
                ])
                ->when($this->filter, function($query) {
                    return call_user_func($this->filter, $query);
                });

            return $query->get();
        };

        // Fetch metrics for both periods
        $currentPeriod = $getMetrics($currentRange);
        $previousPeriod = $getMetrics($previousRange);

        if ($this->output instanceof \Closure) {
            return call_user_func($this->output, $currentPeriod, $previousPeriod);
        }

        return $this->defaultOutput($currentPeriod, $previousPeriod);
    }

    public function defaultOutput(Collection $currentPeriod, Collection $previousPeriod): array
    {
        $current = $currentPeriod->first();
        $previous = $previousPeriod->first();

        return self::formatStats($current, $previous);
    }

    public static function formatStats($current, $previous)
    {
        // Extract current period metrics
        $currentViews = (int)($current?->views ?? 0);
        $currentUniqueViews = (int)($current?->unique_views ?? 0);
        $currentStarts = (int)($current?->starts ?? 0);
        $currentConversions = (int)($current?->conversions ?? 0);
        $currentBounces = (int)($current?->bounces ?? 0);
        $currentRevenue = new Money(
            (int)(($current?->revenue ?? 0)),
            new Currency($current?->currency ?? 'USD')
        );
        $currentStarts = (int)($current?->starts ?? 0);

        // Extract previous period metrics
        $previousViews = (int)($previous?->views ?? 0);
        $previousUniqueViews = (int)($previous?->unique_views ?? 0);
        $previousStarts = (int)($previous?->starts ?? 0);
        $previousConversions = (int)($previous?->conversions ?? 0);
        $previousBounces = (int)($previous?->bounces ?? 0);
        $previousRevenue = new Money(
            (int)(($previous?->revenue ?? 0) * 100), // Convert to cents
            new Currency($previous?->currency ?? 'USD')
        );

        // Calculate rates
        $currentBounceRate = $currentViews > 0
            ? round(($currentBounces / $currentViews) * 100, 1)
            : 0;

        $previousBounceRate = $previousViews > 0
            ? round(($previousBounces / $previousViews) * 100, 1)
            : 0;

        $currentConversionRate = $currentUniqueViews > 0
            ? round(($currentConversions / $currentUniqueViews) * 100, 1)
            : 0;

        $previousConversionRate = $previousUniqueViews > 0
            ? round(($previousConversions / $previousUniqueViews) * 100, 1)
            : 0;

        // Calculate differences
        $uniqueViewsDiff = $previousUniqueViews > 0
            ? round((($currentUniqueViews - $previousUniqueViews) / $previousUniqueViews) * 100, 1)
            : null;

        $conversionsDiff = $previousConversions > 0
            ? round((($currentConversions - $previousConversions) / $previousConversions) * 100, 1)
            : null;

        $revenueDiff = !$previousRevenue->isZero()
            ? $currentRevenue->subtract($previousRevenue)
                ->divide($previousRevenue->getAmount())
                ->multiply(100)
                ->getAmount()
            : null;

        return [
            'views_count' => [
                'label' => 'Views',
                'value' => $currentViews,
            ],
            'unique_views' => [
                'label' => 'Unique Views',
                'value' => $currentUniqueViews,
                'diff_percentage' => $uniqueViewsDiff,
                'diff_relative' => $currentUniqueViews - $previousUniqueViews,
            ],
            'starts_count' => [
                'value' => $currentStarts,
            ],
            'bounce_rate' => [
                'label' => 'Bounce Rate',
                'value' => $currentBounceRate > 0 ? $currentBounceRate . '%' : '0%',
                'diff_percentage' => $previousBounceRate > 0
                    ? round($currentBounceRate - $previousBounceRate, 1)
                    : null,
                'negate' => true,
                'diff_relative' => round($currentBounceRate - $previousBounceRate, 1),
            ],
            'conversions_count' => [
                'label' => 'Conversions',
                'value' => $currentConversions,
                'diff_percentage' => $conversionsDiff,
                'diff_relative' => $currentConversions - $previousConversions,
            ],
            'conversion_rate' => [
                'label' => 'Conversion Rate',
                'value' => $currentConversionRate > 0
                    ? $currentConversionRate . '%'
                    : '0%',
                'diff_percentage' => $previousConversionRate > 0
                    ? round($currentConversionRate - $previousConversionRate, 1)
                    : null,
                'diff_relative' => $currentConversionRate - $previousConversionRate,
            ],
            'revenue_sum' => [
                'label' => 'Revenue',
                'value' => ChargeFormatter::formatShort($currentRevenue),
                'diff_percentage' => $revenueDiff,
                'formatter' => 'currency',
                'diff_relative' => $currentRevenue->subtract($previousRevenue)->divide(100)->getAmount(),
            ],
        ];
    }

    public function getActivityTimeSeries(): array
    {
        $dateRange = $this->range->getDateRange();
        $start = $dateRange->start;
        $end = $dateRange->end;

        // Configure grouping based on range type
        $grouping = match($this->range) {
            StatDateRange::today,
            StatDateRange::yesterday => [
                'sql' => 'DATE_FORMAT(a.created_at, "%Y-%m-%d %H:00:00")',
                'format' => 'Y-m-d H:00:00',
                'interval' => '1 hour',
                'period_start' => $start->startOfHour(),
                'period_end' => $end->endOfHour()
            ],
            StatDateRange::last_7_days,
            StatDateRange::last_30_days,
            StatDateRange::this_month,
            StatDateRange::last_month => [
                'sql' => 'DATE(a.created_at)',
                'format' => 'Y-m-d',
                'interval' => '1 day',
                'period_start' => $start->startOfDay(),
                'period_end' => $end->endOfDay()
            ],
            StatDateRange::last_365_days,
            StatDateRange::this_year,
            StatDateRange::last_year,
            StatDateRange::all_time => [
                'sql' => 'DATE_FORMAT(a.created_at, "%Y-%m-01")',
                'format' => 'Y-m-01',
                'interval' => '1 month',
                'period_start' => $start->startOfMonth(),
                'period_end' => $end->startOfMonth()
            ]
        };

        // Get actual data
        $query = DB::table('intel_activities as a')
            ->select([
                DB::raw($grouping['sql'] . ' as date'),
                DB::raw('COUNT(DISTINCT a.collector_id) as views'),
                DB::raw('SUM(a.has_started) as starts'),
                DB::raw('SUM(a.has_completed) as conversions'),
                DB::raw('SUM(CASE WHEN a.has_entered = 1 AND a.has_started = 0 THEN 1 ELSE 0 END) as bounces'),
            ])
            ->join('intel_scenarios as s', 's.id', '=', 'a.scenario_id')
            ->where('a.client_id', $this->client->id)
            ->whereBetween('a.created_at', [$start, $end])
            ->when($this->filter, function($query) {
                return call_user_func($this->filter, $query);
            })
            ->groupBy(DB::raw($grouping['sql']))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Create complete range with appropriate interval
        $period = CarbonPeriod::create(
            $grouping['period_start'],
            $grouping['interval'],
            $grouping['period_end']
        );

        return collect($period)
            ->map(function($date) use ($query, $grouping) {
                $dateKey = $date->format($grouping['format']);
                $row = $query[$dateKey] ?? null;

                return [
                    'date' => $dateKey,
                    'views' => (int) ($row?->views ?? 0),
                    'starts' => (int) ($row?->starts ?? 0),
                    'converts' => (int) ($row?->conversions ?? 0),
                    'bounces' => (int) ($row?->bounces ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    public function getRevenueTrend(): array
    {
        $end = Carbon::now();
        $start = $end->copy()->subDays(29); // 30 days including today
        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays(29);

        // Function to get cumulative revenue for a date range
        $getCumulativeRevenue = function (Carbon $start, Carbon $end) {
            $data = DB::table('intel_activities as ia')
                ->select([
                    DB::raw('DATE(ia.created_at) as date'),
                    DB::raw('COALESCE(SUM(CAST(ca.proceeds_amount_gross as DECIMAL(20,2))), 0) as daily_revenue')
                ])
                ->join('intel_scenarios as is', 'is.id', '=', 'ia.scenario_id')
                ->leftJoin('convert_attributions as ca', function($join) {
                    $join->on('ca.activity_id', '=', 'ia.id')->whereNotNull('ca.proceeds_amount_gross');
                })
                ->where('ia.client_id', $this->client->id)
                ->whereBetween('ia.created_at', [
                    $start->copy()->setTimezone('UTC'),
                    $end->copy()->setTimezone('UTC')
                ])
                ->when($this->filter, function($query) {
                    return call_user_func($this->filter, $query);
                })
                ->groupBy(DB::raw('DATE(ia.created_at)'))
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            // Build cumulative amounts for each day
            $cumulative = 0;
            return collect(CarbonPeriod::create($start, $end))
                ->map(function($date) use ($data, &$cumulative) {
                    $dateKey = $date->format('Y-m-d');
                    $cumulative += (float)($data[$dateKey]->daily_revenue ?? 0);

                    return [
                        'date' => $dateKey,
                        'amount' => $cumulative
                    ];
                })
                ->values()
                ->all();
        };

        $current = $getCumulativeRevenue($start, $end);
        $previous = $getCumulativeRevenue($previousStart, $previousEnd);

        // Get final amounts for comparison
        $currentTotal = end($current)['amount'] ?? 0;
        $previousTotal = end($previous)['amount'] ?? 0;

        return [
            'current' => $current,
            'previous' => $previous,
            'previous_period_name' => $previousStart->format('F'),
            'totals' => [
                'current' => $currentTotal,
                'difference' => $currentTotal - $previousTotal,
                'difference_percentage' => $previousTotal > 0
                    ? (($currentTotal - $previousTotal) / $previousTotal) * 100
                    : null,
            ]
        ];
    }}
