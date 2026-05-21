<?php

namespace App\Models\Billing;

use App\Billing\BillingService;
use App\Database\Model;
use App\Integration\Connectors\ConnectorInterface;
use App\Integration\Connectors\StripeConnector;
use App\Models\Management\Operation;
use App\Models\Management\Organization;
use App\Models\Twin;
use App\Services\Billing\ProviderInitialSyncService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $lookup_key
 * @property string $type
 * @property string $name
 * @property string $secret
 * @property string $environment
 * @property array $config
 * @property Organization $organization
 * @property ConnectorInterface $connector
 * @property string $current_state
 * @property BillingService $service,
 * @property string $external_id
 */
class BillingProvider extends Model
{
    use HasFactory;

    protected $table = 'billing_providers';

    protected $fillable = [
        'organization_id',
        'lookup_key',
        'type',
        'name',
        'secret',
        'config',
        'environment',
    ];

    protected $casts = [
        'secret' => 'encrypted:json',
        'config' => 'json',
    ];

    protected ?ConnectorInterface $connector = null;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function operations(): MorphMany
    {
        return $this->morphMany(Operation::class, 'model');
    }

    public function twins()
    {
        return $this->morphMany(Twin::class, 'connector');
    }

    public function import(bool $cancel = false): self
    {
        if (! $cancel && $this->current_state === 'importing') {
            return $this;
        }

        $this->current_state = 'importing';
        $this->save();

        ProviderInitialSyncService::dispatch($this);

        return $this;
    }

    public function getExternalIdAttribute()
    {
        return $this->lookup_key;
    }

    public function externalUrl(): ?string
    {
        return match ($this->type) {
            'stripe' => 'https://dashboard.stripe.com/'.$this->lookup_key,
            'stripe_test' => 'https://dashboard.stripe.com/test/'.$this->lookup_key,
            default => null,
        };
    }

    public function getConnectorAttribute(): ?ConnectorInterface
    {
        return $this->connector ?: match ($this->service) {
            BillingService::Stripe => new StripeConnector($this),
            default => null,
        };
    }

    public function getServiceAttribute(): ?BillingService
    {
        return match ($this->type) {
            'stripe', 'stripe_test' => BillingService::Stripe,
            default => null,
        };
    }

    public function getPublicApiKey(): ?string
    {
        return match($this->service) {
            BillingService::Stripe => Arr::get($this->config, 'access_token.stripe_publishable_key'),
            default => null,
        };
    }

    public static function integrationDescriptor(string $type)
    {
        return match ($type) {
            'stripe' => [
                'id' => 'stripe',
                'name' => 'Stripe',
                'label' => 'Stripe',
                'description' => 'Stripe is a payment processor',
                'logo' => '/stripe-icon.jpg',
            ],
            'stripe_test' => [
                'id' => 'stripe_test',
                'name' => 'Stripe Sandbox',
                'label' => 'Stripe <span style="color:#c84801; font-weight: bold;">Test Mode</span>',
                'description' => 'Connect to your stripe Sandbox (Test) account',
                'logo' => '/stripe-icon.jpg',
            ],
            default => null,
        };
    }

    public function getRouteKey()
    {
        return implode('@', [
            $this->getAttribute('lookup_key'),
            $this->getAttribute('environment'),
        ]);
    }

    public function getRouteKeyName()
    {
        return 'lookup_key';
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        [$lookupKey, $env] = array_pad(explode('@', $value), 2, 1);

        $query = $query->where('environment', $env);

        return parent::resolveRouteBindingQuery($query, $lookupKey, $field);
    }
}
