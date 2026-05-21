<?php

namespace App\Http\Controllers\Client;

use App\Client\ClientAuthorization;
use App\Http\Controllers\Controller;
use App\Integration\Connectors\StripeConnector;
use App\Models\Account\Customer;
use App\Models\Intelligence\Activity;
use App\Models\Store\Purchase;
use App\Models\Twin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StoreTransactionAction extends Controller
{
    public function __invoke(Request $request, ClientAuthorization $auth)
    {
        $activityId = $request->get('activity');

        $activity = Activity::query()
            ->where('uuid', $activityId)
            ->first();

        $billing = $auth->client->billingProvider;

        if (is_null($billing)) {
            throw ValidationException::withMessages([
                'bsp' => 'Billing Provider not configured',
            ]);
        }

        $stripe = new StripeConnector(
            billing: $billing,
        );

        $scenario = $activity?->scenario;
        $flow = $scenario?->flow;

        if (is_null($flow)) {
            throw ValidationException::withMessages([
                'flow' => 'Flow is required',
            ]);
        }

        if (is_null($activity->customer)) {
            $stripeClient = $billing->connector->getStripeClient();

            $stripeCustomer = $stripeClient->customers->create([
            ]);

            Customer::unguard();
            $customer = Customer::query()
                ->firstOrCreate([
                    'organization_id' => $billing->organization_id,
                    'billing_provider_id' => $billing->id,
                    'reference_id' => $stripeCustomer->id,
                ], [
                    'name' => $stripeCustomer->name,
                    'email' => $stripeCustomer->email,
                    'currency' => $stripeCustomer->currency,
                    'reference_created_at' => Carbon::createFromTimestamp($stripeCustomer->created),
                ]);
            Customer::reguard();

            $auth->agent->associateWithCustomer($customer);

            $activity->customer_id = $customer->id;
            $activity->save();

            $twin = Twin::query()
                ->updateOrCreate([
                    'reference_id' => $stripeCustomer->id,
                    'connector_id' => $billing->id,
                    'connector_type' => 'billing_provider',
                    'organization_id' => $billing->organization_id,
                ], Twin::fromStripeObject($stripeCustomer)->toArray());

            $twin->link($customer);
        }

        // for the scenario, calculate the purchasables
        $params = [
            'success_url' => $request->get('redirect_url'),
            'mode' => 'subscription',
            'customer' => $auth->customer?->getRouteKey(),
            'line_items' => [
                [
                    'price' => 'price_1PK4FFFmvUKqVS2HKhgVdBu7',
                    'quantity' => 1,
                ],
            ],
        ];

        Log::info(logname(), $params);

        $session = $stripe->getStripeClient()->checkout->sessions->create($params);

        // TODO
        Purchase::create([

        ]);

        if ($activity) {
            $activity->process_id = $session->id;
            $activity->process_type = 'stripe_checkout';
            $activity->save();
        }

        return $session;
    }
}
