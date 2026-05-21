<?php

namespace App\Models\Billing;

use App\Database\Model;
use App\Models\Account\Customer;
use App\Models\Management\Organization;
use App\Models\Pricing\Plan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Schedule groups together subscriptions and manages changes to a customer's subscription.
 *
 * @property Collection<Subscription> $subscriptions
 * @property Collection<Phase>        $phases
 * @property Customer                 $customer
 *
 * @property int $id
 * @property int $customer_id
 * @property int $organization_id
 * @property int $latest_payment_id
 * @property int $first_purchase_id

 * @property string $country_code
 * @property string $currency_code
 * @property string $payment_state
 * @property string $cancel_reason
 * @property string $cancel_message
 * @property bool $will_auto_renew
 * @property Carbon|null $resume_at
 * @property Carbon|null $start_at
 * @property Carbon|null $end_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $cancelled_at
 */
class Schedule extends Model
{
    use HasFactory;

    // todo: is_trial_period An indicator of whether an auto-renewable subscription is in the free trial period.
    // todo: is_in_intro_offer_period An indicator of whether an auto-renewable subscription is in an introductory price period.
    // todo: is_in_billing_retry_period An indicator of whether an auto-renewable subscription is in the billing retry period.
    // todo: expiration_intent:
    // 1: The customer canceled their subscription.
    // 2: "Billing error; for example, the customer’s payment information is no longer valid.
    // 3: The customer didn’t consent to an auto-renewable subscription price increase that requires customer consent, allowing the subscription to expire.
    // 4: The product wasn’t available for purchase at the time of renewal.
    // 5: The subscription expired for some other reason

    protected $table = 'billing_schedules';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function plans(): BelongsToMany
    {
        return $this
            ->belongsToMany(Plan::class, 'billing_schedule_plans');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'schedule_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class);
    }
}
