<?php

namespace App\Models;

use App\Database\Model;
use App\Models\Billing\BillingProvider;
use App\Models\Management\Organization;
use App\Models\Values\TwinType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder;
use Stripe\Customer;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeObject;
use Stripe\Subscription;

/**
 * @property BillingProvider $connector
 * @property int $id
 * @property int $organization_id
 * @property int $connection_id
 * @property TwinType $type
 * @property string $reference_id
 * @property array|null $data
 * @property Carbon $reference_created_at
 * @property Organization $organization
 * @property Model $linkable
 */
class Twin extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'connection_id',
        'connector_id',
        'connector_type',
        'type',
        'reference_id',
        'data',
        'reference_created_at',
    ];

    protected $hidden = [
        'connector',
        'organization',
    ];

    protected $casts = [
        'data' => 'json',
        'reference_created_at' => 'datetime',
    ];

    public static function fromStripeObject(StripeObject $object): Twin
    {
        return self::unguarded(function () use ($object) {
            return new static([
                'type' => get_class($object),
                'reference_id' => $object->id,
                'data' => $object->toArray(),
                'reference_created_at' => ! is_null($object->created)
                    ? Carbon::createFromTimestampUTC($object->created)
                    : null,
            ]);
        });
    }

    protected StripeObject $_stripeObject;

    public function object()
    {
        if (isset($this->_stripeObject)) {
            return $this->_stripeObject;
        }

        $model = $this->type;

        if (! class_exists($model)) {
            return null;
        }

        return $this->_stripeObject = $model::constructFrom($this->data, []);
    }

    public function link(Model $model): void
    {
        $this->linkable()->associate($model)->save();
    }

    public function linkable()
    {
        return $this->morphTo();
    }

    public function sync()
    {
        /* @var Subscription $object */
        $object = $this->object()->refresh();

        if ($object) {
            $this->data = $object->toArray();
            $this->save();
        }

        return $this;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function connector(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): ?string
    {
        $dash = 'https://dashboard.stripe.com/'.$this->connector->lookup_key.'/'.($this->connector->environment === 'test' ? 'test/' : '');

        $path = match($this->type) {
            Customer::class => "customers/{$this->reference_id}",
            Subscription::class => "subscriptions/{$this->reference_id}",
            Price::class => "prices/{$this->reference_id}",
            Product::class => "products/{$this->reference_id}",
            default => null,
        };

        return $dash.$path;
    }

    public function scopeBsp(\Illuminate\Database\Eloquent\Builder $query, BillingProvider $bsp): void
    {
        $query->where([
            'connector_id' => $bsp->id,
            'connector_type' => $bsp->getMorphClass(),
        ]);
    }
}
