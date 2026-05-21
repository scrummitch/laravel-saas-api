<?php

namespace App\Services\Checkout;

use App\Billing\BillingService;
use App\Billing\Coupon;
use App\Models\Account\Customer;
use App\Models\Billing\BillingProvider;
use App\Models\Client;
use App\Models\Store\Purchase;
use App\Models\Twin;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Stripe\ConfirmationToken;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class InitializePaymentMethodService extends BaseService
{
    protected ?BillingProvider $billing = null;

    public function __construct(
        public Client $client,
        public Purchase $purchase,
    )
    {
        $this->billing = $client->billingProvider ?: $client->organization->liveBillingProvider;
    }

    public function __invoke(array $data): Purchase
    {
        return match ($this->billing->service) {
            BillingService::Stripe => $this->initializeStripe($data),
            default => throw new \Exception('Unsupported billing service'),
        };
    }

    private function initializeStripe(array $data): Purchase
    {
        $token = Arr::get($data, 'props.confirmation_token');

        if (empty($token)) {
            throw ValidationException::withMessages([
                'payment_method' => ['Confirmation token is required'],
            ]);
        }

        /* @var BillingProvider $billing */
        $stripe = $this->billing->connector->getStripeClient();

        $confirmationToken = $stripe
            ->confirmationTokens
            ->retrieve($token);

        $customer = $this->purchase->customer ?? $this->ensureCustomerIsCreated($stripe, $confirmationToken);

        try {
            $setupIntent = $stripe->setupIntents->create([
                'customer' => $customer->reference_id,
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],
                'confirm' => true,
                'confirmation_token' => $confirmationToken,
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
                'connector_id' => $this->billing->id,
                'connector_type' => 'billing_provider',
                'organization_id' => $this->client->organization_id,
            ], Twin::fromStripeObject($pm)->toArray());
        Twin::reguard();

        $pms = $customer->payment_methods ?? [];
        array_push($pms, $pmTwin->id);
        $customer->payment_methods = $pms;
        $customer->save();

        // Transaction -> Purchase
        $this->purchase->payment_method_id = $pmTwin->id;
        $this->purchase->save();

        return $this->purchase;
    }

    public function ensureCustomerIsCreated(StripeClient $stripeClient, ConfirmationToken $confirmationToken): Customer
    {
        $billingDetails = $confirmationToken->payment_method_preview->billing_details;

        if (! is_null($confirmationToken->customer)) {
            $stripeCustomer = $stripeClient->customers->retrieve($confirmationToken->customer);
        } else {
            $stripeCustomer = $stripeClient->customers->create([
                'email' => $billingDetails->email,
                'name' => $billingDetails->name,
                'phone' => $billingDetails->phone,
            ]);
        }

        Customer::unguard();
        $customer = Customer::query()
            ->firstOrCreate([
                'organization_id' => $this->client->organization_id,
                'billing_provider_id' => $this->billing->id,
                'reference_id' => $stripeCustomer->id,
            ], [
                'name' => $stripeCustomer->name,
                'email' => $stripeCustomer->email,
                'currency' => $stripeCustomer->currency,
                'reference_created_at' => Carbon::createFromTimestamp($stripeCustomer->created),
            ]);
        Customer::reguard();

        // todo:

        // todo: check coupon exists?
        $agent = $this->purchase->agent;

        if (!is_null($agent)) {
            $agent->associateWithCustomer($customer);

            if ($agent->is_sandbox_user
                && $stripeCustomer->discount === null
                && $this->client->environment === 'live'
            ) {
                $stripeCustomer = $stripeClient->customers->update($stripeCustomer->id, [
                    'coupon' => Coupon::PLANDALF_SANDBOX_CODE,
                ]);
            }
        }


        $this->purchase->customer_id = $customer->id;
        $this->purchase->save();

        $twin = Twin::query()
            ->updateOrCreate([
                'reference_id' => $stripeCustomer->id,
                'connector_id' => $this->billing->id,
                'connector_type' => 'billing_provider',
                'organization_id' => $this->client->organization_id,
            ], Twin::fromStripeObject($stripeCustomer)->toArray());

        $twin->link($customer);

        return $customer;
    }

    private function ensureCustomerExists(Purchase $checkout)
    {
        $customer = $checkout->customer;

        if (! $customer) {
            $customer = new Customer;
            $customer->save();
            $checkout->customer()->associate($customer);
            $checkout->save();
        }

        return $customer;
    }
}
