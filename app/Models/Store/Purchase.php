<?php

namespace App\Models\Store;

use App\Billing\CurrencyCast;
use App\Billing\IntervalCast;
use App\Database\Traits\HasLookupKey;
use App\Models\Account\Agent;
use App\Models\Account\Customer;
use App\Models\Billing\BillingProvider;
use App\Models\Convert\CheckoutState;
use App\Models\Intelligence\Activity;
use App\Models\Management\Organization;
use App\Models\Twin;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Money\Currency;

/**
 * @property int|null    $payment_method_id
 * @property int|null    $activity_id
 * @property int         $organization_id
 * @property int|null    $customer_id
 * @property string      $intent
 *
 * @property string      $provider_name
 * @property string      $provider_id
 *
 * @property CheckoutState $current_state
 * @property Currency $currency
 *
 * @property Carbon|null $completed_at
 *
 * @property BillingProvider $billing_provider
 * @property Collection<PurchaseItem> $items
 * @property Customer|null $customer
 * @property ?CarbonInterval $renew_interval
 * @property int $billing_provider_id
 *
 * @property Carbon $billing_start_at
 */
class Purchase extends \App\Database\Model
{
    use HasFactory,
        HasLookupKey;

    protected $table = 'store_purchases';

    protected $casts = [
        'currency' => CurrencyCast::class,
        'current_state' => CheckoutState::class,
        'renew_interval' => IntervalCast::class,
    ];

    public function getRouteKeyName()
    {
        return 'provider_id';
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function agent()
    {
        return $this->hasOneThrough(Agent::class, Activity::class, 'id', 'id', 'activity_id', 'id');
    }

    public function payment_method()
    {
        return $this->belongsTo(Twin::class);
    }

    public function billing_provider(): BelongsTo
    {
        return $this->belongsTo(BillingProvider::class);
    }

    public function items(): HasMany
    {
        // items -> purchasable
        return $this->hasMany(PurchaseItem::class); // purchasable
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function isFinalised(): bool
    {
        return $this->current_state->isFinalised();
    }

    public function applyCoupon(Twin $coupon)
    {
        DB::table('billing_discounts')
            ->insert([
                'discountable_id' => $this->id,
                'discountable_type' => 'purchase',
                'key' => $coupon->reference_id,
                'coupon_id' => $coupon->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function getBillingStartAtAttribute(): Carbon
    {
        $today = Carbon::now();

        if (! $this->customer) {
            return $today;
        }

        //currently we only support single subscription
        $subscription = $this->customer?->subscriptions->first();

        if (! $subscription) {
            return $today;
        }

        $subscription->loadMissing('twin');

        $trialEndDate = Carbon::createFromTimestamp(data_get($subscription->twin?->data, 'trial_end') ??  $today->timestamp);

        //trial has already elapsed
        if ($trialEndDate->isPast()) {
            return $today;
        }

        return $trialEndDate;
    }
}
