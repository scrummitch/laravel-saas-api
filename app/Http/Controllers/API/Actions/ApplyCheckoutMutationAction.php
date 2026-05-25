<?php

namespace App\Http\Controllers\API\Actions;

use App\Client\ClientAuthorization;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\CheckoutClientResource;
use App\Models\Client;
use App\Models\Pricing\Plan;
use App\Models\Store\Purchase;
use App\Models\Twin;
use App\Models\Values\PlanStatus;
use App\Services\Checkout\CommitCheckoutService;
use App\Services\Checkout\InitializePaymentMethodService;
use Illuminate\Http\Request;

class ApplyCheckoutMutationAction extends Controller
{
    private Client $client;

    public function __invoke(ClientAuthorization $auth, Purchase $purchase, Request $request)
    {
        abort_unless(
            (int) $purchase->organization_id === (int) $auth->client?->organization_id,
            404,
        );

        $action = $request->get('action');

        $this->client = $auth->client;

        if (! method_exists($this, $action)) {
            abort(400, 'Invalid action');
        }

        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($purchase, $auth, $action) : void {
            $scope->setContext('paywall-session', [
                'purchase' => $purchase?->getRouteKey(),
                'collector' => $auth->collector?->getRouteKey(),
                'mutation' => $action,
                'client' => $auth->client->getRouteKey(),
                'environment' => $auth->client->environment,
            ]);
        });

        try {
            $checkout = $this->{$action}($purchase, $request);
        } catch (\Throwable $e) {
            if ($purchase) {
                // todo
//                DB::table('convert_paywall_events')
//                    ->insert([
//                        'session_id' => $transaction->id,
//                        'collector_id' => $transaction->collector_id,
//                        'name' => 'error:backend',
//                        'data' => json_encode([
//                            'class' => get_class($e),
//                            'message' => $e->getMessage(),
//                        ]),
//                        'created_at' => now(),
//                    ]);
            }

            throw $e;
        }

        return new CheckoutClientResource($checkout);
    }

    public function updateRenewalInterval(Purchase $purchase, Request $request)
    {
        $newInterval = $request->json('props.interval');

        if ($newInterval !== $purchase->renew_interval?->spec()) {
            $purchase->renew_interval = $newInterval;
        }

        foreach ($purchase->items as $item) {
            $currentPlan = $item->purchasable;

            $newPlan = Plan::query()
                ->where('package_id', $currentPlan->package_id)
                ->where('renew_interval', $newInterval)
                ->where('currency', $currentPlan->currency->getCode())
                ->where('status', PlanStatus::Active->value)
                ->with(['charges'])
                ->get()
//                ->filter(fn (Plan $plan) => $plan->charges->first()->amount->isPositive())
                ->first();

            $item->purchasable()->associate($newPlan);
            $item->save();
        }

        $purchase->save();

        return $purchase;
    }

    public function setField(Purchase $purchase, Request $request)
    {
        $key = $request->json('props.key');
        $value = $request->json('props.value');

        if ($key === 'payment_method') {
            // this may not exist quite yet?
            $twin = Twin::query()
                ->where('organization_id', $this->client->organization_id)
                ->where('reference_id', $value)
                ->first();

            abort_unless($twin, 400, 'Invalid payment method');

            $purchase->payment_method()->associate($twin);
            $purchase->save();
        }

        return $purchase;
    }

    public function initPmCollection(Purchase $purchase, Request $request)
    {
        return (new InitializePaymentMethodService(client: $this->client, purchase: $purchase))($request->all());
    }

    public function commit(Purchase $purchase, Request $request)
    {
        $service = new CommitCheckoutService(
            purchase: $purchase,
        );

        $purchase = $service($request->all());

        return $purchase;
    }
}
