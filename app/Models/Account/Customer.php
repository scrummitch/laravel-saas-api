<?php

namespace App\Models\Account;

use App\Billing\CurrencyCast;
use App\Database\Model;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Schedule;
use App\Models\Billing\Subscription;
use App\Models\Catalog\Inclusion;
use App\Models\Management\Organization;
use App\Models\Pricing\Plan;
use App\Models\Twin;
use App\Models\Usage\Summary;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Money\Currency;
use Sentry\Tracing\SpanContext;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\Price;
use Stripe\Subscription as StripeSubscription;

/**
 * Core Customer Object.
 * Should be the centralised location for all customer related data.
 * Customers can exist in multiple systems, this model connects them via links to twins
 *
 * @module Account
 *
 * @property int $id
 * @property Organization $organization
 * @property Twin $twin
 * @property Schedule $schedule
 * @property BillingProvider $billingProvider
 * @property Collection<Schedule> $schedules
 * @property Collection<Subscription> $subscriptions
 * @property Collection<Inclusion> $entitlements
 * @property Twin $coupon
 *
 * @property int $organization_id
 * @property int $connection_id
 * @property int $billing_provider_id
 * @property string $reference_id
 * @property string $name
 * @property string $email
 * @property Currency $currency
 * @property string $timezone
 * @property array $payment_methods
 * @property int $primary_payment_method_id Twin of primary payment method
 * @property int $coupon_id
 * @property ?Carbon $reference_synced_at
 */
class Customer extends Model
{
    use HasFactory,
        SoftDeletes;

    protected $table = 'account_customers';

    protected $casts = [
        'payment_methods' => 'array',
        'currency' => CurrencyCast::class,
        'reference_created_at' => 'datetime',
        'reference_synced_at' => 'datetime',
    ];

    protected $fillable = [
        'organization_id',
        'connection_id',
        'reference_id',
        'name',
        'email',
        'currency',
        'timezone',
        'payment_methods',
        'primary_payment_method_id',
        'external_id',
        'billing_provider_id',
        'coupon',
        'reference_synced_at',
    ];

    public function getRouteKeyName()
    {
        return 'reference_id';
    }

    public function billingProvider(): BelongsTo
    {
        return $this->belongsTo(BillingProvider::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Twin::class);
    }

    public function twin(): MorphOne
    {
        return $this
            ->morphOne(Twin::class, 'linkable')
            ->whereHasMorph('connector', [BillingProvider::class], function ($query) {
                $query->where('connector_type', 'billing_provider');
            });
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function schedule(): HasOneOrMany
    {
        return $this
            ->hasOne(Schedule::class)
            ->latestOfMany();
    }

    public function usageSummaries(): HasMany
    {
        return $this->hasMany(Summary::class);
    }

    public function syncStripe(bool $force = false)
    {
        if (is_null($this->billingProvider)) {
            return;
        }

        if (! is_null($this->reference_synced_at) && $this->reference_synced_at->diffInMinutes(now()) < 5 && ! $force) {
            return;
        }

        $this->syncCustomer();

        try {
            $this->syncSchedule();
        } catch (\Throwable $e) {
            logger()->error('Failed to sync schedule', [
                'customer' => $this->id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    public function syncCustomer(): void
    {
        if (is_null($this->billingProvider)) {
            return;
        }

        $customerSpan = SpanContext::make()
            ->setOp(logname('stripe-customer-get'))
            ->setDescription('Gets stripe customer');

        // 200ms
        $stripeCustomer = \Sentry\trace(fn () => $this->getStripeCustomer(), $customerSpan);

        $this->name = $stripeCustomer->name;
        $this->email = $stripeCustomer->email;
        $this->currency = $stripeCustomer->currency;

        $pmSpan = SpanContext::make()
            ->setOp(logname('stripe-pm-sync'))
            ->setDescription('Syncs stripe payment methods');

        // 1.7ms
        $newStripePaymentMethods = \Sentry\trace(function () {
            $paymentMethods = $this->paymentMethods();

            return collect($this->getStripePaymentMethods())
                ->filter(function (StripePaymentMethod $stripePaymentMethod) use ($paymentMethods) {
                    return $paymentMethods->firstWhere('reference_id', '=', $stripePaymentMethod->id) === null;
                });
        }, $pmSpan);

        // something happens here thats slow?

        foreach ($newStripePaymentMethods as $stripePaymentMethod) {
            $twin = Twin::query()
                ->updateOrCreate([
                    'reference_id' => $stripePaymentMethod->id,
                    'connector_id' => $this->billing_provider_id,
                    'connector_type' => 'billing_provider',
                    'organization_id' => $this->organization_id,
                ], Twin::fromStripeObject($stripePaymentMethod)->toArray());

            $this->payment_methods = array_merge($this->payment_methods ?? [], [$twin->id]);

            if ($twin->reference_id
                && $twin->reference_id === $stripeCustomer->invoice_settings?->default_payment_method
            ) {
                $this->primary_payment_method_id = $twin->id;
            }
        }

        // 15ms
        $twin = $this->twin;
        $twin->data = Twin::fromStripeObject($stripeCustomer)->toArray();
        $twin->save();

        if ($coupon = $stripeCustomer->discount?->coupon) {
            // 18ms
            $twinCoupon = Twin::query()
                ->updateOrCreate([
                    'reference_id' => $coupon->id,
                    'connector_id' => $this->billing_provider_id,
                    'connector_type' => 'billing_provider',
                    'organization_id' => $this->organization_id,
                ], Twin::fromStripeObject($coupon)->toArray());

            $this->coupon()->associate($twinCoupon);
        } else {
            //delete any customer's old coupons
            $this->coupon()->delete();
        }

        if ($this->isDirty()) {
            $this->reference_synced_at = now();
            $this->save();
        }
    }

    public function syncSchedule(): void
    {
        if (is_null($this->billingProvider)) {
            return;
        }

        $this->billingProvider->connector->syncSubscriptions($this);
    }

    public function primaryPaymentMethod(): HasOne
    {
        return $this->hasOne(Twin::class, 'id', 'primary_payment_method_id');
    }

    public function paymentMethods(): Collection
    {
        // NOTE: this takes 50ms ?
        return Twin::query()
            ->whereIn('id', $this->payment_methods ?? [])
            ->get();
    }

    public function getEntitlementsAttribute()
    {
        $this->loadMissing('subscriptions');

        return Inclusion::query()
            ->whereIn('plan_id', $this->subscriptions->pluck('plan_id'))
            ->whereNotNull('feature_id')
            ->get();
    }

    public function getStripeCustomer(): \Stripe\Customer
    {
        return $this->billingProvider
            ->connector
            ->getStripeClient()
            ->customers
            ->retrieve($this->reference_id);
    }

    public function getStripeSubscriptions(): \Stripe\Collection
    {
        return $this->billingProvider
            ->connector
            ->getStripeClient()
            ->subscriptions
            ->all([
                'customer' => $this->reference_id,
            ]);
    }

    public function getStripePaymentMethods(): \Stripe\Collection
    {
        return $this->billingProvider
            ->connector
            ->getStripeClient()
            ->paymentMethods
            ->all([
                'customer' => $this->reference_id,
            ]);
    }

    public function twins(): MorphMany
    {
        return $this
            ->morphMany(Twin::class, 'linkable');
    }

    public function getCurrentBillingInterval(): ?CarbonInterval
    {
        return $this->subscriptions
            ->where('current_state', 'active')
            ->loadMissing(['plan'])
            ->first()
            ?->plan
            ?->renew_interval;
    }
}
