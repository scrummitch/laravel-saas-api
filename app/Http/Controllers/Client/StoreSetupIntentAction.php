<?php

namespace App\Http\Controllers\Client;

use App\Billing\BillingService;
use App\Client\ClientAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Account\Association;
use App\Models\Account\Customer;
use App\Models\Billing\BillingProvider;
use App\Models\Twin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;

class StoreSetupIntentAction extends Controller
{
    public function __invoke(ClientAuthorization $auth, Request $request)
    {
        $client = $auth->client;

        $billing = $client->billingProvider ?: $client->organization->liveBillingProvider;

        return match ($billing->service) {
            BillingService::Stripe => $this->initializeStripe($billing, $request, $auth),
            default => throw new \Exception('Unsupported billing service'),
        };
    }

    private function initializeStripe(?BillingProvider $billing, Request $request, ClientAuthorization $auth): Response
    {
        $token = $request->input('confirmation_token');

        $stripe = $billing->connector->getStripeClient();

        $confirmationToken = $stripe
            ->confirmationTokens
            ->retrieve($token);

//        $customer = $this->ensureCustomerIsCreated($stripe);
        $billingDetails = $confirmationToken->payment_method_preview->billing_details;

        // customer can also be associated via auth
        //

        if (! is_null($confirmationToken->customer)) {
            $stripeCustomer = $stripe->customers->retrieve($confirmationToken->customer);
        } else {
            $stripeCustomer = $stripe->customers->create([
                'email' => $billingDetails->email,
                'name' => $billingDetails->name,
                'phone' => $billingDetails->phone,
            ]);
        }

        Customer::unguard();
        /* @var Customer $customer */
        $customer = \App\Models\Account\Customer::query()
            ->firstOrCreate([
                'organization_id' => $billing->organization_id,
                'billing_provider_id' => $billing->id,
                'reference_id' => $stripeCustomer->id,
            ], [
                'name' => $stripeCustomer->name,
                'email' => $stripeCustomer->email,
                'currency' => $stripeCustomer->currency,
            ]);
        Customer::reguard();

        if ($auth->agent) {
            $auth->agent->associateWithCustomer($customer);
        }

        /* @var Twin $twin */
        $twin = Twin::query()
            ->updateOrCreate([
                'reference_id' => $stripeCustomer->id,
                'connector_id' => $billing->id,
                'connector_type' => 'billing_provider',
                'organization_id' => $billing->organization_id,
            ], Twin::fromStripeObject($stripeCustomer)->toArray());

        $twin->link($customer);

        try {
            $setupIntent = $stripe->setupIntents->create([
                'customer' => $customer->reference_id,
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],
                'confirm' => true,
                'confirmation_token' => $token,
                'usage' => 'off_session',
            ]);
        } catch (ApiErrorException $e) {
            throw ValidationException::withMessages([
                'payment_method' => [$e->getMessage()],
            ]);
        }

        $pm = $stripe->paymentMethods->retrieve($setupIntent->payment_method);
        Twin::unguard();
        $pmTwin = Twin::query()
            ->updateOrCreate([
                'reference_id' => $pm->id,
                'connector_id' => $billing->id,
                'connector_type' => 'billing_provider',
                'organization_id' => $billing->organization_id,
            ], Twin::fromStripeObject($pm)->toArray());
        Twin::reguard();

        $pms = $customer->payment_methods ?? [];
        array_push($pms, $pmTwin->id);
        $customer->payment_methods = $pms;
        $customer->save();

        return response([
            'payment_method' => $setupIntent->payment_method,
            'store' => [
                'setup_intent' => $setupIntent->toArray(),
            ],
        ], 200, [
        ]);
    }
}
