<?php

namespace App\Services\Sync\Stripe;

use App\Billing\ISO4217;
use App\Jobs\Services\Stripe\ImportStripePricesJob;
use App\Models\Account\Customer;
use App\Models\Account\Signal;
use App\Models\Account\SignalID;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Charge;
use App\Models\Billing\Schedule;
use App\Models\Billing\Subscription;
use App\Models\Catalog\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Convert\Attribution;
use App\Models\Convert\CheckoutState;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use App\Models\Store\Purchase;
use App\Models\Store\PurchaseItem;
use App\Models\Twin;
use App\Models\Usage\UsageEvent;
use App\Models\Values\PlanStatus;
use App\Models\Values\PlanType;
use App\Notifications\CreateInvoiceNotification;
use App\Services\Invoices\CustomerUsageService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Money\Currency;
use Money\Money;
use Stripe\Customer as StripeCustomer;
use Stripe\Event;
use Stripe\Invoice as StripeInvoice;
use Stripe\InvoiceItem;
use Stripe\InvoiceLineItem;
use Stripe\LineItem;
use Stripe\Price;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\Source;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Subscription as StripeSubscription;

class StripeEventProcessor
{
    protected ?StripeClient $stripe = null;

    public function __construct(public BillingProvider $billing) {}

    public function handle(Event $event)
    {
        $handler = Str::camel(str_replace('.', ' ', $event->type));

        if (! method_exists($this, $handler)) {
            logger()->debug(logname('eventHandler.notFound'), [
                'handler' => $handler,
            ]);

            return;
        }

        $this->stripe = new StripeClient([
            'api_key' => $this->billing->secret,
            'stripe_account' => $this->billing->external_id,
        ]);

        try {
            $this->{$handler}($event);
        } catch (\Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }

            report($e);
        }
    }

    protected function upsert(StripeObject $object): Twin
    {
        /* @var Twin $twin */
        Twin::unguard();
        $twin = Twin::query()
            ->updateOrCreate([
                'reference_id' => $object->id,
                'connector_id' => $this->billing->id,
                'connector_type' => 'billing_provider',
                'organization_id' => $this->billing->organization_id,
            ], Twin::fromStripeObject($object)->toArray());
        Twin::reguard();

        return $twin;
    }

    protected function ensureCustomerIsCreated(StripeCustomer|string $stripeCustomer): ?Customer
    {
        if (is_string($stripeCustomer)) {
            $stripeCustomer = retry(3, function () use ($stripeCustomer) {
                return StripeCustomer::retrieve($stripeCustomer, $this->clientOpts());
            }, 150);

            if (is_null($stripeCustomer)) {
                throw new \Exception('Customer not found');
            }
        }

        $twin = $this->upsert($stripeCustomer);

        Customer::unguard();
        $customer = Customer::query()
            ->updateOrCreate([
                'organization_id' => $this->billing->organization_id,
                'billing_provider_id' => $this->billing->id,
                'reference_id' => $twin->reference_id,
            ], [
                'email' => $stripeCustomer->email,
                'name' => $stripeCustomer->name,
                'reference_created_at' => $stripeCustomer->created
                    ? Carbon::createFromTimestampUTC($stripeCustomer->created)
                    : now(),
            ]);
        $twin->link($customer);

        return $customer;
    }

    protected function ensurePriceIsCreated(StripePrice|string $price)
    {
        if (is_string($price)) {
            $price = $this->stripe->prices->retrieve($price, null, $this->clientOpts());
        }

        $twin = $this->upsert($price);

        return $twin;
    }

    protected function clientOpts(): array
    {
        return [
            'api_key' => $this->billing->secret,
            'stripe_account' => $this->billing->external_id,
        ];
    }

    public function ensureProductIsCreated(StripeProduct|string $stripeProduct): ?Product
    {
        if (is_string($stripeProduct)) {
            $stripeProduct = $this->stripe->products->retrieve($stripeProduct, null, $this->clientOpts());
        }

        if (! $stripeProduct) {
            return null;
        }

        $twin = $this->upsert($stripeProduct);

        return Product::upsertFromTwin($twin);
    }

    protected function customerCreated(Event $event): void
    {
        $stripeCustomer = StripeCustomer::constructFrom(data_get($event->data, 'object'));

        $this->ensureCustomerIsCreated($stripeCustomer);
    }

    protected function customerUpdated(Event $event): void
    {
        $stripeCustomer = StripeCustomer::constructFrom(data_get($event->data, 'object'));

        $this->ensureCustomerIsCreated($stripeCustomer);
    }

    protected function customerDeleted(Event $event): void
    {
        $stripeCustomer = StripeCustomer::constructFrom(data_get($event->data, 'object'));

        $customer = $this->ensureCustomerIsCreated($stripeCustomer);

        $customer?->delete();
    }

    protected function customerSourceCreated(Event $event)
    {
        /* @var Source $source */
        $source = Source::constructFrom(data_get($event->data, 'object'));

        $customer = $this->ensureCustomerIsCreated($source->customer);

        $twin = $this->upsert($source);
    }

    public function customerSourceDeleted(Event $event)
    {
        $twin = $this->upsert(Source::constructFrom(data_get($event->data, 'object')));

        $twin->delete();
    }

    public function customerSourceExpiring(Event $event)
    {
        $this->upsert(Source::constructFrom(data_get($event->data, 'object')));

        // todo:
    }

    public function customerSourceUpdated(Event $event)
    {
        $this->upsert(Source::constructFrom(data_get($event->data, 'object')));
    }

    public function refund(Event $event)
    {
        $refund = data_get($event->data, 'object');
        $customer = $this->ensureCustomerIsCreated($refund->customer);
        $charge = $this->upsert($refund->charge)->linkable;

//        Signal::make(SignalID::Return)
//            ->for($customer)
//            ->from($charge)
//            ->amount($refund->amount)
//            ->withMeta([
//                'refund_id' => $refund->id,
//                'charge_id' => $refund->charge,
//            ])
//            ->emit();
    }

    # attribute for event
    public function paymentMethodAttached(Event $event)
    {
        $paymentMethod = data_get($event->data, 'object');
        $customer = $this->ensureCustomerIsCreated($paymentMethod->customer);
        $twin = $this->upsert($paymentMethod);

        Signal::make(SignalID::Preauthorize)
            ->for($customer)
//            ->from($twin->linkable)
            ->withMeta([
                'payment_method_id' => $paymentMethod->id,
                'type' => $paymentMethod->type
            ])
            ->emit();
    }

    public function customerSubscriptionCreated(Event $event)
    {
        $subscription = StripeSubscription::constructFrom(data_get($event->data, 'object'));
        $customer = $this->ensureCustomerIsCreated($subscription->customer);
        $this->upsert($subscription);

        $this->billing->connector->syncSubscriptions($customer);

        // Check customer history
        if ($this->hadPreviousAbandonedTrial($customer)) {
            $lastAbandonDate = $this->getLastAbandonDate($customer);

            // If they abandoned more than 30 days ago
            if ($lastAbandonDate?->isBefore(now()->subDays(30))) {
                $plan = $this->getMainPlan($subscription);

                Signal::make(SignalID::Resurrection)
                    ->for($customer)
//                    ->to($plan)
                    ->amount($subscription->items->data[0]->price->unit_amount)
                    ->withMeta([
                        'subscription_id' => $subscription->id,
                        'previous_trial_end' => $lastAbandonDate,
                        'days_since_abandon' => $lastAbandonDate?->diffInDays(now())
                    ])
                    ->emit();
            } else {
                // Within 30 days is a "Reactivate"
                Signal::make(SignalID::Reactivate)
                    ->for($customer)
//                    ->to($this->getMainPlan($subscription))
                    ->amount($subscription->items->data[0]->price->unit_amount)
                    ->withMeta([
                        'subscription_id' => $subscription->id,
                    ])
                    ->emit();
            }
        }
        // Handle direct subscriptions (no trial)
        else if (!$subscription->trial_end && $subscription->status === 'active') {

            $mainPlan = $this->getMainPlan($subscription);

            if (!$mainPlan) {
                return;
            }

            Signal::make(SignalID::Acquisition)
                ->for($customer)
                ->to($mainPlan)
                ->amount($subscription->items->data[0]->price->unit_amount)
                ->withMeta([
                    'subscription_id' => $subscription->id,
                ])
                ->emit();
        }
    }

    public function customerSubscriptionUpdated(Event $event): void
    {
        $subscription = StripeSubscription::constructFrom(data_get($event->data, 'object'));
        $this->upsert($subscription);
        $previousAttributes = data_get($event, 'data.previous_attributes');
        $customer = $this->ensureCustomerIsCreated($subscription->customer);
        $this->billing->connector->syncSubscriptions($customer);

        // Trial conversion
        if ($this->isTrialConversion($subscription, $previousAttributes)) {
            $signal = Signal::make(SignalID::Convert)
                ->for($customer);

            $currency = new Currency($subscription->currency ?? 'USD');
            $amount = new Money(0, $currency);

            foreach ($subscription->items as $item) {
                $charge = Twin::query()
                    ->where('reference_id', $item->price->id)
                    ->where('connector_id', $this->billing->id)
                    ->where('connector_type', 'billing_provider')
                    ->first()
                    ?->linkable;
                $plan = $charge->plans()->first();

                $signal->keep($plan, $item->quantity);

                $charge = $plan->charges->first();

                $amount = $amount->add($charge->calculateAmount($item->quantity));
            }

            $signal->amount($amount)
                ->effectiveAt(Carbon::createFromTimestamp($subscription->current_period_end))
                ->withMeta([
                    'subscription_id' => $subscription->id,
                ])
                ->emit();

            return;
        }

        // Handle plan changes during trial
        if ($subscription->status === 'trialing' && isset($previousAttributes['items'])) {
            $signal = Signal::make(SignalID::Switch)
                ->for($customer);

            $currency = new Currency($subscription->currency ?? 'USD');
            $oldAmount = new Money(0, $currency);

            foreach (data_get($previousAttributes, 'items.data') as $item) {
                $charge = Twin::query()
                    ->where('reference_id', $item->price->id)
                    ->where('connector_id', $this->billing->id)
                    ->where('connector_type', 'billing_provider')
                    ->first()
                    ?->linkable;
                $plan = $charge->plans()->first();
                $signal->from($plan, $item->quantity);
                $charge = $plan->charges->first();
                $oldAmount = $oldAmount->add($charge->calculateAmount($item->quantity));
            }

            $newAmount = new Money(0, $currency);
            foreach ($subscription->items as $item) {
                $charge = Twin::query()
                    ->where('reference_id', $item->price->id)
                    ->where('connector_id', $this->billing->id)
                    ->where('connector_type', 'billing_provider')
                    ->first()
                    ?->linkable;
                $plan = $charge->plans()->first();
                $signal->to($plan, $item->quantity);
                $charge = $plan->charges->first();
                $newAmount = $newAmount->add($charge->calculateAmount($item->quantity));
            }

            $difference = $oldAmount->greaterThan($newAmount)
                ? (new Money(0, $currency))->subtract($oldAmount->subtract($newAmount))
                : $newAmount->subtract($oldAmount);

            logger()->info('amounts!!!!!!!!!!!!!!!!!!!', [
                'amount' => $difference,
                'old' => $oldAmount,
                'new' => $newAmount,
            ]);

            $signal
                ->amount($difference)
                ->effectiveAt(Carbon::createFromTimestamp($subscription->trial_end))
                ->withMeta([
                    'subscription_id' => $subscription->id,
                ])
                ->emit();
            return;
        }

        // Handle regular plan changes (non-trial)
        if (isset($previousAttributes['items'])) {
            $this->determineSubscriptionChange($subscription, $previousAttributes);

            return;
        }

        // Handle pause/resume
        if (isset($previousAttributes['pause_collection'])) {
            $type = $subscription->pause_collection ? SignalID::Pause : SignalID::Resume;

            Signal::make($type)
                ->for($customer)
                ->effectiveAt($subscription->pause_collection?->resumes_at
                    ? Carbon::createFromTimestamp($subscription->pause_collection->resumes_at)
                    : null)
                ->withMeta([
                    'subscription_id' => $subscription->id,
                    'resumes_at' => $subscription->pause_collection?->resumes_at
                ])
                ->emit();

            return;
        }

        // Handle cancellation
        if ($subscription->cancel_at_period_end && isset($previousAttributes['cancel_at_period_end'])) {
            $signal = Signal::make(SignalID::Cancel)
                ->for($customer);
            $currency =  new Currency($subscription->currency ?? 'USD');
            $amount = new Money(0, $currency);

            foreach ($subscription->items as $item) {
                $charge = Twin::query()
                    ->where('reference_id', $item->price->id)
                    ->where('connector_id', $this->billing->id)
                    ->where('connector_type', 'billing_provider')
                    ->first()
                    ->linkable;
                $plan = $charge->plans()->first();

                $signal->keep($plan, $item->quantity);

                $charge = $plan->charges->first();

                $amount = $amount->subtract($charge->calculateAmount($item->quantity));
            }

            $signal
                ->effectiveAt(Carbon::createFromTimestamp($subscription->current_period_end))
                ->amount($amount)
                ->withMeta([
                    'subscription_id' => $subscription->id,
                ])
                ->emit();
        }
    }

    private function hadPreviousAbandonedTrial(Customer $customer): bool
    {
        return Signal::where('customer_id', $customer->id)
            ->where('type', SignalID::Abandon)
            ->exists();
    }

    private function getLastAbandonDate(Customer $customer): ?Carbon
    {
        return Signal::where('customer_id', $customer->id)
            ->where('type', SignalID::Abandon)
            ->latest()
            ->value('created_at');
    }

    public function customerSubscriptionDeleted(Event $event)
    {
        $subscription = StripeSubscription::constructFrom(data_get($event->data, 'object'));
        $customer = $this->ensureCustomerIsCreated($subscription->customer);
        $twin = $this->upsert($subscription);

        // Emit cancel signal
        $signal = Signal::make(SignalID::Cancel)
            ->for($customer);

        $currency = new Currency($subscription->currency ?? 'USD');
        $amount = new Money(0, $currency);

        // Add each subscription item to the signal
        foreach ($subscription->items->data as $item) {
            $charge = Twin::query()
                ->where('reference_id', $item->price->id)
                ->where('connector_id', $this->billing->id)
                ->where('connector_type', 'billing_provider')
                ->first()
                ?->linkable;

            if ($charge && ($plan = $charge->plans()->first())) {
                $signal->from($plan, $item->quantity);
                $amount = $amount->subtract($charge->calculateAmount($item->quantity));
            }
        }

        $signal
            ->amount($amount)
            ->effectiveAt(Carbon::createFromTimestamp($subscription->canceled_at ?? time()))
            ->withMeta([
                'subscription_id' => $subscription->id,
            ])
            ->emit();

        // Clean up subscriptions and twin
        $customer->syncStripe(force: true);

        $schedule = Schedule::query()
            ->where('customer_id', $customer->id)
            ->first();

        if (!is_null($schedule)) {
            DB::table('billing_subscriptions')
                ->where('schedule_id', $schedule->id)
                ->where('twin_id', $twin->id)
                ->delete();
        }

        $twin->delete();
    }

    public function customerSubscriptionPaused(Event $event)
    {
        // apply phase information here?
    }

    public function customerSubscriptionResumed(Event $event) {}

    // TODO: note(this isnt really an abandon, its a pre-abandon!)
    public function customerSubscriptionTrialWillEnd(Event $event)
    {
        $subscription = StripeSubscription::constructFrom(data_get($event->data, 'object'));
        $customer = $this->ensureCustomerIsCreated($subscription->customer);

        if (!$subscription->default_payment_method) {
            $signal = Signal::make(SignalID::Abandon)
                ->for($customer);

            $currency =  new Currency($subscription->currency ?? 'USD');
            $amount = new Money(0, $currency);

            foreach ($subscription->items as $item) {
                $charge = Twin::query()
                    ->where('reference_id', $item->price->id)
                    ->where('connector_id', $this->billing->id)
                    ->where('connector_type', 'billing_provider')
                    ->first()
                    ->linkable;
                $plan = $charge->plans()->first();

                $signal->keep($plan, $item->quantity);

                $charge = $plan->charges->first();

                $amount = $amount->subtract($charge->calculateAmount($item->quantity));
            }

            $signal
                ->effectiveAt(Carbon::createFromTimestamp($subscription->trial_end))
                ->withMeta([
                    'subscription_id' => $subscription->id,
                ])
                ->emit();
        }
    }


    protected function determineSubscriptionChange(
        StripeSubscription $subscription,
        StripeObject $previousAttributes
    ): ?Signal {
        $currentItems = $subscription->items->data;
        $previousItems = $previousAttributes->items->data;

        $customer = $this->ensureCustomerIsCreated($subscription->customer);
        $currency = new Currency($subscription->currency ?? 'USD');

        // Calculate previous amount and plans
        $previousAmount = new Money(0, $currency);
        $previousPlans = collect();
        foreach ($previousItems as $item) {
            $charge = Twin::query()
                ->where('reference_id', $item->price->id)
                ->where('connector_id', $this->billing->id)
                ->where('connector_type', 'billing_provider')
                ->first()
                ?->linkable;

            if ($charge && ($plan = $charge->plans()->first())) {
                $previousPlans->push([
                    'plan' => $plan,
                    'quantity' => $item->quantity,
                    'charge' => $charge
                ]);
                $previousAmount = $previousAmount->add($charge->calculateAmount($item->quantity));
            }
        }

        // Calculate current amount and plans
        $currentAmount = new Money(0, $currency);
        $currentPlans = collect();
        foreach ($currentItems as $item) {
            $charge = Twin::query()
                ->where('reference_id', $item->price->id)
                ->where('connector_id', $this->billing->id)
                ->where('connector_type', 'billing_provider')
                ->first()
                ?->linkable;

            if ($charge && ($plan = $charge->plans()->first())) {
                $currentPlans->push([
                    'plan' => $plan,
                    'quantity' => $item->quantity,
                    'charge' => $charge
                ]);
                $currentAmount = $currentAmount->add($charge->calculateAmount($item->quantity));
            }
        }

        // First check for add-ons being attached/detached
        $addedPlans = $currentPlans
            ->whereNotIn('plan.id', $previousPlans->pluck('plan.id'));
        $removedPlans = $previousPlans
            ->whereNotIn('plan.id', $currentPlans->pluck('plan.id'));

        if ($addedPlans->where('plan.type', PlanType::addon)->isNotEmpty()) {
            $signal = Signal::make(SignalID::Attach)
                ->for($customer);

            foreach ($addedPlans as $added) {
                $signal->to($added['plan'], $added['quantity']);
            }

            return $signal
                ->amount($currentAmount->subtract($previousAmount))
                ->effectiveAt(Carbon::createFromTimestamp($subscription->current_period_end))
                ->withMeta([
                    'subscription_id' => $subscription->id,
                    'addon_ids' => $addedPlans->pluck('plan.id')->toArray()
                ])
                ->emit();
        }

        if ($removedPlans->where('plan.type', PlanType::addon)->isNotEmpty()) {
            $signal = Signal::make(SignalID::Detach)
                ->for($customer);

            foreach ($removedPlans as $removed) {
                $signal->from($removed['plan'], $removed['quantity']);
            }

            return $signal
                ->amount($currentAmount->subtract($previousAmount))
                ->effectiveAt(Carbon::createFromTimestamp($subscription->current_period_end))
                ->withMeta([
                    'subscription_id' => $subscription->id,
                    'addon_ids' => $removedPlans->pluck('plan.id')->toArray()
                ])
                ->emit();
        }

        // Then check for quantity changes (Expansion/Contraction)
        $quantityChanged = $currentPlans->some(function($current) use ($previousPlans) {
            $previous = $previousPlans->first(fn($p) => $p['plan']->id === $current['plan']->id);
            return $previous && $previous['quantity'] !== $current['quantity'];
        });

        if ($quantityChanged) {
            $signal = Signal::make(
                $currentAmount->greaterThan($previousAmount) ? SignalID::Expansion : SignalID::Contraction
            )->for($customer);

            foreach ($currentPlans as $current) {
                $previous = $previousPlans->first(fn($p) => $p['plan']->id === $current['plan']->id);
                if ($previous && $previous['quantity'] !== $current['quantity']) {
                    $delta = $current['quantity'] - $previous['quantity'];

                    $signal->keep($current['plan'], $current['quantity'], $delta);
                }
            }

            return $signal->amount($currentAmount->subtract($previousAmount))
                ->effectiveAt(Carbon::createFromTimestamp($subscription->current_period_end))
                ->withMeta([
                    'subscription_id' => $subscription->id,
                ])
                ->emit();
        }


        // Finally check for plan/price changes (Upgrade/Downgrade)
        if (!$currentAmount->equals($previousAmount)) {
            $signal = Signal::make(
                $currentAmount->greaterThan($previousAmount) ? SignalID::Upgrade : SignalID::Downgrade
            )->for($customer);

            foreach ($previousPlans as $previous) {
                $signal->from($previous['plan'], $previous['quantity'], 0);
            }

            foreach ($currentPlans as $current) {
                $signal->to($current['plan'], $current['quantity'], 0);
            }

            return $signal
                ->amount($currentAmount->subtract($previousAmount))
                ->effectiveAt(Carbon::createFromTimestamp($subscription->current_period_end))
                ->withMeta([
                    // this is the stripe object ID
                    'subscription_id' => $subscription->id,
                ])
                ->emit();
            // associate with a pending signal?
        }

        return null;
    }

    private function handleAddonChanges(
        Customer $customer,
        Collection $oldItems,
        Collection $newItems,
        StripeSubscription $subscription
    ): ?Signal {
        $addedItems = $newItems->diffBy($oldItems, fn($item) => $item->price->id);
        $removedItems = $oldItems->diffBy($newItems, fn($item) => $item->price->id);

        if ($addedItems->isNotEmpty()) {
            return Signal::make(SignalID::Attach)
                ->for($customer)
                ->to($this->getMainPlan($newItems))
                ->withMeta([
                    'subscription_id' => $subscription->id,
                    'addon_ids' => $addedItems->pluck('price.id')->toArray()
                ])
                ->emit();
        }

        if ($removedItems->isNotEmpty()) {
            return Signal::make(SignalID::Detach)
                ->for($customer)
                ->from($this->getMainPlan($oldItems))
                ->withMeta([
                    'subscription_id' => $subscription->id,
                    'addon_ids' => $removedItems->pluck('price.id')->toArray()
                ])
                ->emit();
        }

        return null;
    }

    private function getMainPlan(Collection|StripeSubscription $items): ?Plan
    {
        // Get the first price ID from either a Collection or StripeSubscription
        $priceId = $items instanceof Collection
            ? ($items->first()['price']['id'] ?? $items->first()['price'])
            : (is_string($items->items->data[0]->price) ? $items->items->data[0]->price : $items->items->data[0]->price->id);

        // Look up the Twin record for this price
        $priceTwin = Twin::query()
            ->where('reference_id', $priceId)
            ->where('connector_id', $this->billing->id)
            ->where('connector_type', 'billing_provider')
            ->first();

        // Return the linked Plan
        return $priceTwin?->linkable instanceof Plan ? $priceTwin->linkable : null;
    }

    private function isTrialConversion(
        StripeSubscription $subscription,
        $previousAttributes = null
    ): bool {
        return isset($previousAttributes['status']) &&
            $previousAttributes['status'] === 'trialing' &&
            $subscription->status === 'active' &&
            !isset($previousAttributes['items']);
    }

    public function checkoutSessionCompleted(Event $event)
    {
        $session = \Stripe\Checkout\Session::constructFrom(data_get($event->data, 'object'));
        $customer = $this->ensureCustomerIsCreated($session->customer);

        // todo: for non ui, we need to add a basic flow to calculate all

        // find the activity?

        $purchase = Purchase::query()
            ->firstOrNew([
                'provider_name' => 'stripe',
                'provider_id' => $session->id,
                'billing_provider_id' => $this->billing->id,
            ]);

        $purchase->organization_id = $this->billing->organization_id;

//        $purchase->activity_id = ''; only if not already associated? maybe?
        $purchase->current_state = CheckoutState::COMPLETED; // open, complete, expired
        $purchase->customer_id = $customer->id;

        // get the setup and payment intents out
        //

        $purchase->intent = 'upgrade'; // is it?
        $purchase->renew_interval = null;
        $purchase->currency = $session->currency;
        $purchase->completed_at = now();
        // first or create?

        //* @property null|string $recovered_from The ID of the original expired Checkout Session that triggered the recovery flow.
        $items = [];

        $purchase->expires_at = Carbon::createFromTimestampUTC($session->expires_at);

        foreach ($session->line_items ?? [] as $li) {
            if (is_null($li->price)) {
                continue;
            }

            // find the plan, via the charge.
            $twin = Twin::query()
                ->first([
                    'organization_id' => $this->billing->organization_id,
                    'connector_id' => $this->billing->id,
                    'connector_type' => $this->billing->getMorphClass(),
                    'reference_id' => $li->price,
                ]);

            if (is_null($twin)) {
                continue;
            }

            $charge = $twin->linkable;

            $plan = Plan::query()
                ->whereHas('charges', function ($query) use ($charge) {
                    $query->where('billing_charges.id', $charge->id);
                })
                ->first();

            if (is_null($plan)) {
                continue;
            }

            $currency = new Currency($li->currency);

            $item = new PurchaseItem();
            $item->purchasable()->associate($plan);
            $item->quantity = $li->quantity;
            $item->amount_discount = new Money($item->amount_discount, $currency);
            $item->amount_tax = new Money($item->amount_tax, $currency);
            $item->amount_total = new Money($item->amount_total, $currency);
            $item->amount_subtotal = new Money($item->amount_subtotal, $currency);

            $items[] = $item;
        }

        //
        $purchase->save();

        $purchase->items()->saveMany($items);



    }

    public function paymentIntentCreated(Event $event)
    {
        $intent = \Stripe\PaymentIntent::constructFrom(data_get($event->data, 'object'));

        $twin = $this->upsert($intent);
    }

    //PAYMENT_INTENT_AMOUNT_CAPTURABLE_UPDATED
    //PAYMENT_INTENT_CANCELED
    //PAYMENT_INTENT_CREATED
    //PAYMENT_INTENT_PARTIALLY_FUNDED
    //PAYMENT_INTENT_PAYMENT_FAILED
    //PAYMENT_INTENT_PROCESSING
    //PAYMENT_INTENT_REQUIRES_ACTION
    //PAYMENT_INTENT_SUCCEEDED
    public function paymentIntentSucceeded(Event $event)
    {
        $intent = \Stripe\PaymentIntent::constructFrom(data_get($event->data, 'object'));

        $twin = $this->upsert($intent);

//        $checkout = DB::table('convert_checkouts')
//            ->where([
//                'checkout_identifier' => $intent->id,
//                'has_started' => true,
//            ])
//            ->first();
//
//        if (! is_null($checkout)) {
//            DB::table('convert_checkouts')
//                ->where('checkout_identifier', $intent->id)
//                ->update([
//                    'has_completed' => true,
//                ]);
//        }
    }

    public static function ensureChargeIsCreated(Product $product, Twin $twin, BillingProvider $billing): ?Charge
    {
        /* @var Charge $charge */
        $charge = $twin->linkable;

        if (is_null($charge)) {
            $charge = new Charge;
            $charge->organization_id = $billing->organization_id;
        }

        $charge->product_id = $product->id;
        $charge->fillFromTwin($twin);
        $charge->save();
        $twin->link($charge);

        return $charge;
    }

    public static function ensurePlanIsCreated(Charge $charge, ?Twin $twin, ?Package $package)
    {
        $product = $charge->product;

        if (! $twin) {
            return null;
        }

        /* @var Plan $plan */
        $plan = Plan::query()
            ->whereHas('charges', function ($query) use ($charge) {
                $query->where('billing_charges.id', $charge->id);
            })
            ->first();

        $name = $twin->object()->lookup_key ?? $twin->object()->id;

        if (is_null($plan)) {
            $plan = Plan::query()
                ->where([
                    'organization_id' => $charge->organization_id,
                    'name' => $name,
                ])
                ->firstOrNew();
        }

        $plan->organization_id = $charge->organization_id;
        $plan->package_id = $package?->id;

        if ($package) {
            $plan->name = implode(' ', [
                $package->name,
                'Plan',
                $charge->currency->getCode(),
                $charge->twins()->first()->object()->recurring?->interval,
            ]);
        } else {
            $plan->name = $name;
        }

        if (!$plan->exists) {
            $plan->setLookupKey($name);
            $plan->type = ImportStripePricesJob::determinePlanType($twin->object(), $product);
            $plan->display_name = implode(' ', [$product->name, 'per', ImportStripePricesJob::englishInterval($twin->object())]);
            $plan->description = null;
        }

        $plan->renew_interval = ImportStripePricesJob::determineInterval($twin->object());
        $plan->billing_anchor = 'anniversary';
        $plan->invoice_interval = ImportStripePricesJob::determineInterval($twin->object());
        $plan->currency = ISO4217::make($twin->object()->currency);
        $plan->trial_length = $price->recurring?->trial_period_days ?? 0;
        $plan->trial_credit = null;
        $plan->trial_unit = 'day';
        $plan->status = $twin->object()->active ? PlanStatus::Active : PlanStatus::Archived;
        $plan->save();

        try {
            Inclusion::unguard();
            $inclusion = Inclusion::query()
                ->firstOrCreate([
                    'plan_id' => $plan->id,
                    'charge_id' => $charge->id,
                    'product_id' => $product->id,
                ], [
                    'display_name' => $product->name,
                ]);
            Inclusion::reguard();
        } catch (\Illuminate\Database\QueryException $e) {
            logger()->error(logname('attachCharge'), [
                'charge_id' => $charge->id,
                'plan_id' => $plan->id,
            ]);
        }

        return $plan;
    }

    public function priceCreated(Event $event): void
    {
        $price = StripePrice::constructFrom(data_get($event->data, 'object'));

        $twin = $this->upsert($price);

        $product = $this->ensureProductIsCreated($price->product);

        $charge = self::ensureChargeIsCreated($product, $twin, $this->billing);

        $existingPackage = Package::query()
            ->where('organization_id', $this->billing->organization_id)
            ->where('lookup_key', $product->lookup_key)
            ->first();

        if (! is_null($existingPackage)) {
            self::ensurePlanIsCreated($charge, $twin, $existingPackage);
        }
    }

    public function priceUpdated(Event $event)
    {
        $price = StripePrice::constructFrom(data_get($event->data, 'object'));

        $twin = $this->upsert($price);

        $product = $this->ensureProductIsCreated($price->product);

        $charge = $this->ensureChargeIsCreated($product, $twin, $this->billing);
    }

    public function priceDeleted(Event $event)
    {
        $price = StripePrice::constructFrom(data_get($event->data, 'object'));

        $twin = $this->upsert($price);

        $product = $this->ensureProductIsCreated($price->product);

        $charge = $this->ensureChargeIsCreated($product, $twin, $this->billing);
    }

    public function productCreated(Event $event): void
    {
        $stripeProduct = StripeProduct::constructFrom(data_get($event->data, 'object'));

        $product = $this->ensureProductIsCreated($stripeProduct);

        logger()->info(logname('productCreated'), [
            'stripe_product_id' => $stripeProduct?->id,
            'catalog_product_id' => $product?->id,
        ]);
    }

    public function productDeleted(Event $event): void
    {
        $stripeProduct = StripeProduct::constructFrom(data_get($event->data, 'object'));

        $product = $this->ensureProductIsCreated($stripeProduct);

        logger()->info(logname('productDeleted'), [
            'stripe_product_id' => $stripeProduct?->id,
            'catalog_product_id' => $product?->id,
        ]);
    }

    public function productUpdated(Event $event): void
    {
        $stripeProduct = StripeProduct::constructFrom(data_get($event->data, 'object'));

        $product = $this->ensureProductIsCreated($stripeProduct);

        logger()->info(logname('productUpdated'), [
            'stripe_product_id' => $stripeProduct?->id,
            'catalog_product_id' => $product?->id,
        ]);
    }

    public function invoiceUpdated(Event $event)
    {
        /* @var StripeInvoice $invoice */
        $invoice = $event->data->object;

        logger()->info(logname(), [
            'event' => $event->id,
            'invoice_id' => $invoice->id,
        ]);
    }

    public function invoiceCreated(Event $event)
    {
        /* @var StripeInvoice $invoice */
        $invoice = $event->data->object;

        logger()->info(logname(), [
            'event' => $event->id,
            'invoice_id' => $invoice->id,
        ]);

        $customerTwin = Twin::query()
            ->where('reference_id', $invoice->customer)
            ->where('organization_id', $this->billing->organization_id)
            ->first();

        $customer = $customerTwin?->linkable ?? $this->ensureCustomerIsCreated($invoice->customer);

        if (is_null($customer)) {
            logger()->warning(logname('customerNotFound'), [
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer,
            ]);

            return;
        }

        // filter all invoice line items by subscriptions only
        collect($invoice->lines)
            ->filter(fn (InvoiceLineItem $line) => $line->type === StripeSubscription::OBJECT_NAME)
            ->map(function (InvoiceLineItem $line) {
                $twin = Twin::query()
                    ->where('reference_id', $line->subscription)
                    ->where('organization_id', $this->billing->organization_id)
                    ->first();

                return $twin?->linkable;
            })
            ->filter()
            ->each(function (Schedule $schedule) use ($customer, $invoice) {
                logger()->info(logname('invoiceItem.schedule'), [
                    'schedule_id' => $schedule->id,
                ]);

                $chargeableInclusions = Inclusion::query()
                    ->join('pricing_plans', 'pricing_plans.id', '=', 'catalog_inclusions.plan_id')
                    ->join('billing_subscriptions', 'billing_subscriptions.plan_id', '=', 'pricing_plans.id')
                    ->where('billing_subscriptions.schedule_id', $schedule->id)
                    ->with(['charge'])
                    ->get();

                /* @var Inclusion $inclusion */
                foreach ($chargeableInclusions as $inclusion) {
                    if ($inclusion->reset_anchor !== 'invoice') {
                        continue;
                    }

                    $charge = $inclusion->charge;

                    if (empty($charge)) {
                        continue;
                    }

                    $subscription = $schedule
                        ->subscriptions
                        ->where('plan_id', $inclusion->plan_id)
                        ->first();

                    // for a specific subscription
                    // inclusion is on hte PLAN
                    $usageService = new CustomerUsageService($customer, $subscription);

                    $b = $usageService->boundaries();
                    logger()->info(logname('invoiceItem.boundaries'), $b);

                    $agg = UsageEvent::query()
                        ->whereBetween('created_at', [$b['charges_from_datetime'], $b['charges_to_datetime']])
                        ->where('customer_id', $customer->id)
                        ->count();

                    $amount = $charge->amount->multiply($agg);

                    if ($charge->minimum_billable_usage > 0 || $amount < $charge->minimum_billable_usage) {
                        logger()->info(logname('invoiceItem.skip'), [
                            'charge_id' => $charge->id,
                            'subscription_id' => $subscription->id,
                            'amount' => $amount->getAmount(),
                            'minimum_billable_usage' => $charge->minimum_billable_usage,
                        ]);

                        continue;
                    }

                    if ($amount < $charge->amount_minimum_spend) {
                        $amount = $charge->amount_minimum_spend;
                    }

                    $attr = [
                        'customer' => $customer->reference_id,
                        'amount' => $amount->getAmount(),
                        'currency' => $charge->currency->getCode(),
                        'description' => $charge->invoice_description,
                        'invoice' => $invoice->id,
                        'metadata' => [
                            'plandalf_id' => $charge->getRouteKey(),
                        ],
                    ];
                    logger()->info(logname('invoiceItem.create'), $attr);

                    // will be as an inclusion on the plan the user is subscribed to
                    if (app()->runningUnitTests()) {
                        InvoiceItem::create($attr, $this->clientOpts());
                        $subscription->invoiced_at = now();
                        $subscription->save();
                    } else {
                        Notification::route('slack', config('services.slack.webhook_url'))
                            ->notify(new CreateInvoiceNotification(
                                boundaries: $b,
                                amount: $amount->getAmount(),
                                invoiceId: $invoice->id,
                                billing: $this->billing,
                                customer: $schedule->customer,
                            ));
                    }
                }
            });

        if ($invoice->billing_reason !== 'subscription_cycle') {

            Signal::make(SignalID::Invoicing)
                ->for($customer)
                ->amount($invoice->amount_due)
                ->withMeta([
                    'invoice_id' => $invoice->id,
                ])
                ->emit();
        }

    }

    public function invoicePaymentSucceeded(Event $event)
    {
        $invoice = StripeInvoice::constructFrom(data_get($event->data, 'object'));
        $customer = $this->ensureCustomerIsCreated($invoice->customer);

        if ($invoice->billing_reason === 'subscription_cycle') {
            Signal::make(SignalID::Renewal)
                ->for($customer)
                ->amount($invoice->amount_paid)
                ->withMeta([
                    'invoice_id' => $invoice->id,
                    'subscription_id' => $invoice->subscription,
                ])
                ->emit();
        }

        foreach ($invoice->lines?->data ?? [] as $line) {
            if ($line->type === 'subscription' || $line->type === 'invoiceitem') {
                $twin = Twin::query()
                    ->where('reference_id', $line->subscription)
                    ->where('organization_id', $this->billing->organization_id)
                    ->first();

                $schedule = $twin?->linkable;

                if (!$twin) {
                    continue;
                }


                $attribution = Attribution::query()
                    ->where('purchase_id', $twin->id)
                    ->first();

                logger()->info(logname('🎉'), [
                    'schedule_id' => $schedule?->id,
                    'twin_id' => $twin->id,
                    'invoice_id' => $invoice->id,
                    'attr_id' => $attribution?->id,
                    'billing_provider_id' => $this->billing->getRouteKey(),
                ]);
                $attribution?->ingestStripeInvoice($line);
            } else {
                logger()->info('😭', [
                    'line_type' => $line->type,
                    'id' => $line->id,
                    'invoice_id' => $invoice->id,
                ]);
            }
        }
    }

    // signal -> controller->createSignal() ->transmit(signal) -> emit(signal)

    private function handleInvoicePaid(StripeInvoice $invoice): ?Signal
    {
        if ($invoice->billing_reason === 'subscription_cycle') {
            $customer = $this->ensureCustomerIsCreated($invoice->customer);

            return Signal::make(SignalID::Renewal)
                ->for($customer)
                ->amount($invoice->amount_paid)
                ->withMeta([
                    'invoice_id' => $invoice->id,
                    'subscription_id' => $invoice->subscription,
                ])
                ->emit();
        }

        return null;
    }

    private function detectQuantityChanges(Collection $oldItems, Collection $newItems): int
    {
        $oldQuantity = $oldItems->first()['quantity'] ?? 1;
        $newQuantity = $newItems->first()['quantity'] ?? 1;

        return $newQuantity - $oldQuantity;
    }

    private function detectTierChange(Collection $oldItems, Collection $newItems): int
    {
        $oldAmount = $oldItems->first()['price']['unit_amount'] ?? 0;
        $newAmount = $newItems->first()['price']['unit_amount'] ?? 0;

        return $newAmount - $oldAmount;
    }

}
