<?php

namespace App\Models\Management;

use App\Billing\Currency;
use App\Billing\CurrencyCast;
use App\Database\Model;
use App\Database\Traits\HasNiceUlids;
use App\Models\Account\Customer;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Charge;
use App\Models\Billing\Schedule;
use App\Models\Catalog\Feature;
use App\Models\Catalog\FeatureSet;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductFamily;
use App\Models\Client;
use App\Models\Convert\Theme;
use App\Models\Convert\Flow;
use App\Models\Intelligence\Scenario;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use App\Models\Twin;
use App\Models\Usage\Metric;
use App\Models\User;
use App\Observers\OrganizationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int    $id
 * @property string $name
 * @property string $invite_code
 * @property User   $owner
 * @property Collection<User> $owners
 * @property Collection<Scheme> $schemes
 * @property Collection<Customer> $customers
 * @property Collection<Operation> $operations
 * @property Collection<Client> $clients
 * @property Collection<Feature> $features
 * @property Collection<Product> $products
 * @property Collection<User> $users
 * @property int $live_billing_provider_id
 * @property int $test_billing_provider_id
 * @property ?BillingProvider $liveBillingProvider
 * @property \Money\Currency $default_currency
 */
#[ObservedBy(OrganizationObserver::class)]
class Organization extends Model
{
    use HasFactory,
        HasNiceUlids;

    protected $table = 'mgmt_organizations';

    public function casts()
    {
        return [
            'flags' => 'json',
            'default_currency' => CurrencyCast::class,
        ];
    }

    protected $with = ['clients'];

    protected $fillable = [
        'name',
    ];

    protected static function booted(): void
    {
        static::creating(function (Organization $org) {
            if (empty($org->invite_code)) {
                $org->invite_code = md5(Str::random(32));
            }
        });
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function themes(): HasMany
    {
        return $this->hasMany(Theme::class);
    }

    public function theme(): HasOne
    {
        return $this->hasOne(Theme::class)->latestOfMany();
    }

    public function schemes(): HasMany
    {
        return $this->hasMany(Scheme::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mgmt_memberships');
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(Flow::class);
    }

    public function scenarios()
    {
        return $this->hasMany(Scenario::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'owner');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function productFamilies(): HasMany
    {
        return $this->hasMany(ProductFamily::class);
    }

    public function twins(): HasMany
    {
        return $this->hasMany(Twin::class);
    }

    public function billingProviders(): HasMany
    {
        return $this->hasMany(BillingProvider::class);
    }

    public function liveBillingProvider(): BelongsTo
    {
        if (empty($this->live_billing_provider_id)) {
            tap($this->billingProviders()->first(), function ($provider) {
                if (empty($provider)) {
                    return null;
                }

                $this->live_billing_provider_id = $provider->id;
                $this->save();
            });
        }

        return $this->belongsTo(BillingProvider::class, 'live_billing_provider_id');
    }

    public function testBillingProvider(): BelongsTo
    {
        if (empty($this->test_billing_provider_id)) {
            tap($this->billingProviders()->first(), function ($provider) {
                if (empty($provider)) {
                    return null;
                }

                $this->test_billing_provider_id = $provider->id;
                $this->save();
            });
        }

        return $this->belongsTo(BillingProvider::class, 'test_billing_provider_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class)->chaperone();
    }

    public function liveClient(): Client
    {
        return $this->clients->firstWhere('environment', 'live');
    }

    public function testClient(): Client
    {
        return $this->clients->firstWhere('environment', 'test');
    }

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    public function featureSets(): HasMany
    {
        return $this->hasMany(FeatureSet::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(Metric::class);
    }

    public function getBooleanFlag($key, $default = false): bool
    {
        return isset($this->flags[$key]) && is_bool($this->flags[$key])
            ? $this->flags[$key]
            : $default;
    }
}
