<?php

namespace App\Models\Billing;

use App\Database\Model;
use App\Models\Account\Customer;
use App\Models\Management\Organization;
use App\Models\Pricing\Plan;
use App\Models\Twin;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Organization $organization
 * @property Customer     $customer
 * @property Schedule     $schedule
 * @property Subscription $previousSubscription
 * @property Plan         $plan
 * @property Twin         $twin
 *
 * @property int      $id
 * @property int      $organization_id
 * @property int      $customer_id
 * @property int      $schedule_id
 * @property int|null $previous_subscription_id
 * @property int      $plan_id
 * @property int      $twin_id
 * @property int      $quantity
 * @property string   $current_state pending|terminated|active|cancelled|paused|unknown
 *
 * @property Carbon $start_at    Date this Subscription started or is meant to start
 * @property Carbon $end_at      Date this Subscription ends or is meant to end
 * @property Carbon $cancel_at   Date this Subscription should be or was cancelled at
 * @property Carbon $invoiced_at Date this Subscription was last invoiced
 * @property Carbon $renewed_at  Date this Subscription was last renewed
 * @property Carbon $created_at  Date this Subscription was created
 * @property Carbon $updated_at  Date this Subscription was last updated
 */
class Subscription extends Model
{
    use HasFactory;

    protected $table = 'billing_subscriptions';

    protected $guarded = [];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'cancel_at' => 'datetime',
        'invoiced_at' => 'datetime',
        'renewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function twin(): BelongsTo
    {
        return $this->belongsTo(Twin::class);
    }

    public static function mapStripeStatusToState(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'trialing', 'past_due', 'active' => 'active',
            'canceled' => 'cancelled',
            'incomplete' => 'pending',
            'incomplete_expired', 'unpaid' => 'terminated',
            'paused' => 'paused',
            default => 'unknown',
        };
    }

    public function getIsTrialAttribute(): bool
    {
        $trialEndDate = data_get($this->twin?->data, 'trial_end');

        if (! $trialEndDate) {
            return false;
        }

        return Carbon::createFromTimestamp($trialEndDate)->isFuture();
    }

    public function scopeActive($query)
    {
        return $query->whereIn('current_state', ['active', 'pending']);
    }
}
