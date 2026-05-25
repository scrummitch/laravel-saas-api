<?php

namespace App\Models\Account;

use App\Billing\CurrencyCast;
use App\Database\Model;
use App\Models\Intelligence\Activity;
use App\Observers\SignalObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Money\Currency;
use Money\Money;

/**
 * @property SignalID $type
 * @property Customer $customer
 * @property int $customer_id
 * @property Money|null $amount
 * @property string|null $amount_raw
 * @property Currency $currency
 * @property array|null $metadata
 * @property Carbon|null $effective_at
 * @property Carbon $created_at
 * @property Collection<SignalDependency> $dependencies
 */
#[ObservedBy(SignalObserver::class)]
class Signal extends Model
{
    public $table = 'intel_signals';

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'client_id',
        'customer_id',
        'activity_id',
        'convert_activity_id',
        'type',
        'amount_raw',
        'currency',
        'metadata',
        'effective_at',
    ];

    protected $casts = [
        'type' => SignalID::class,
        'currency' => CurrencyCast::class,
        'metadata' => 'array',
        'effective_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(SignalDependency::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function getAmountAttribute(): ?Money
    {
        if (!$this->amount_raw || !$this->currency) {
            return null;
        }

        return new Money($this->amount_raw, $this->currency);
    }

    public static function make(SignalID $type): SignalBuilder
    {
        return new SignalBuilder($type);
    }
}
