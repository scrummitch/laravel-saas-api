<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Account\Customer;
use App\Models\Billing\Subscription;
use App\Models\Catalog\Inclusion;
use App\Models\Usage\UsageEvent;
use App\Notifications\CreateInvoiceNotification;
use App\Services\Invoices\CustomerUsageService;
use App\Services\Subscriptions\DatesService;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Stripe\InvoiceItem;

class CustomerUsageController extends Controller
{
    private const int MAX_HISTORIC_PERIODS = 12;
    private const int PER_PAGE = 3;

    public function current(Customer $customer, Request $request)
    {
        $subscription = $request->filled('subscription')
            ? Subscription::retrieve($request->get('subscription'))
            : $customer
                ->subscriptions()
                ->where('current_state', 'active')
                ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No subscription found for this customer'
            ], 400);
        }

        $usageService = new CustomerUsageService($customer, $subscription);

        $boundaries = $usageService->boundaries();

        $chargeableInclusions = Inclusion::query()
            ->join('pricing_plans', 'pricing_plans.id', '=', 'catalog_inclusions.plan_id')
            ->join('billing_subscriptions', 'billing_subscriptions.plan_id', '=', 'pricing_plans.id')
            ->where('billing_subscriptions.customer_id', $customer->id)
            ->where('billing_subscriptions.current_state', 'active')
            ->with(['charge', 'metric'])
            ->select('catalog_inclusions.*')
            ->get();

        $usage = $this->calculateUsageForPeriod(
            $customer,
            $subscription,
            $chargeableInclusions,
            Carbon::make($boundaries['charges_from_datetime']),
            Carbon::make($boundaries['charges_to_datetime'])
        );

        return $usage;
    }

    public function past(Customer $customer, Request $request)
    {
        $subscription = $request->filled('subscription')
            ? Subscription::retrieve($request->get('subscription'))
            : $customer
                ->subscriptions()
                ->where('current_state', 'active')
                ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No subscription found for this customer'
            ], 400);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', self::PER_PAGE);

        // Generate billing periods using CustomerUsageService
        $periods = $this->generateBillingPeriods($customer, $subscription);

        // Create a paginator
        $paginatedPeriods = new LengthAwarePaginator(
            $periods->forPage($page, $perPage),
            $periods->count(),
            $perPage,
            $page,
            ['path' => route('api/customers.usage.past', [$customer])]
        );

        $chargeableInclusions = $this->getChargeableInclusions($customer);

        $usageData = $paginatedPeriods
            ->map(function ($period) use ($customer, $chargeableInclusions, $subscription) {
                return $this->calculateUsageForPeriod(
                    $customer,
                    $subscription,
                    $chargeableInclusions,
                    $period['charges_from_datetime'],
                    $period['charges_to_datetime']
                );
            })
            ->values();

        $maxPeriods = intval(ceil($subscription->start_at->diffInMonths(now())));

        return response()->json([
            'max_periods' => $maxPeriods,
            'total_periods' => $periods->count(),
            'all_periods' => $periods,
            'paginated_periods' => $paginatedPeriods,
            'data' => $usageData,
            'meta' => [
                'current_page' => $paginatedPeriods->currentPage(),
                'from' => $paginatedPeriods->firstItem(),
                'last_page' => $paginatedPeriods->lastPage(),
                'per_page' => $paginatedPeriods->perPage(),
                'to' => $paginatedPeriods->lastItem(),
                'total' => $paginatedPeriods->total(),
            ],
            'links' => [
                'first' => $paginatedPeriods->url(1),
                'last' => $paginatedPeriods->url($paginatedPeriods->lastPage()),
                'prev' => $paginatedPeriods->previousPageUrl(),
                'next' => $paginatedPeriods->nextPageUrl(),
            ]
        ]);
    }

    private function generateBillingPeriods(Customer $customer, Subscription $subscription): Collection
    {
        $periods = new Collection();
        $billingDate = now();
        $maxPeriods = intval(ceil($subscription->start_at->diffInMonths(now())));

        for ($i = 0; $i < $maxPeriods; $i++) {
            $dates = DatesService::instance(
                subscription: $subscription,
                // set to now
                billingAt: $billingDate,
                // not current usage, Since we want completed periods
                wantsCurrentUsage: false
            );

            // Only add period if it's after subscription start and is a complete period
            if (Carbon::parse($dates->chargesFromDatetime())->gt($subscription->start_at)) {
                $periods->push([
                    'from_datetime' => $dates->fromDatetime(),
                    'to_datetime' => $dates->toDatetime(),
                    'charges_from_datetime' => $dates->chargesFromDatetime(),
                    'charges_to_datetime' => $dates->chargesToDatetime(),
                ]);
            } else {
                break;
            }

            // Move to previous period using from_datetime as the next billing date
            // This ensures we're properly moving by the subscription's billing period
            $billingDate = Carbon::parse($dates->chargesFromDatetime());
        }

        return $periods;
    }

    private function getChargeableInclusions(Customer $customer): Collection
    {
        return Inclusion::query()
            ->join('pricing_plans', 'pricing_plans.id', '=', 'catalog_inclusions.plan_id')
            ->join('billing_subscriptions', 'billing_subscriptions.plan_id', '=', 'pricing_plans.id')
            ->where('billing_subscriptions.customer_id', $customer->id)
            ->where('billing_subscriptions.current_state', 'active')
            ->where('catalog_inclusions.reset_anchor', 'invoice')
            ->with(['charge', 'metric'])
            ->select('catalog_inclusions.*')
            ->get();
    }

    private function calculateUsageForPeriod(
        Customer $customer,
        Subscription $subscription,
        Collection $inclusions,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $fees = [];

        foreach ($inclusions as $inclusion) {
            if (empty($inclusion->charge) || empty($inclusion->metric)) {
                continue;
            }

            $charge = $inclusion->charge;

            $usage = UsageEvent::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('customer_id', $customer->id)
                ->count();

            $amount = $charge->amount->multiply($usage);

            if ($charge->minimum_billable_usage > 0 && $usage < $charge->minimum_billable_usage) {
                continue;
            }

            if ($amount->lessThan($charge->amount_minimum_spend)) {
                $amount = $charge->amount_minimum_spend;
            }

            $fees[] = [
                'subscription_id' => $subscription->id,
                'amount' => $amount->getAmount(),
                'description' => $charge->invoice_description,
                'usage' => $usage,
                'charge' => [
                    'name' => $charge->name,
                    'invoicing_interval' => $charge->invoicing_interval,
                    'currency' => $charge->currency->getCode(),
                    'type' => $charge->type,
                    'amount_minimum_spend' => $charge->amount_minimum_spend?->getAmount(),
                    'minimum_billable_usage' => $charge->minimum_billable_usage,
                    'amount' => $charge->amount->getAmount(),
                    'mode' => $charge->mode,
                ],
                'metric' => [
                    'id' => $inclusion->metric->getRouteKey(),
                    'event_name' => $inclusion->metric->event_name,
                    'aggregation' => $inclusion->metric->aggregation->value,
                    'type' => $inclusion->metric->type,
                ]
            ];
        }

        return [
            'period_start_at' => $startDate->toDateTimeString(),
            'period_end_at' => $endDate->toDateTimeString(),
            'amount' => collect($fees)->sum('amount'),
            'currency' => 'USD',
            'fees' => $fees,
        ];
    }
}
