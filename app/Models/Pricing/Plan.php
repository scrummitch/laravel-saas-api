<?php

namespace App\Models\Pricing;

use App\Billing\CurrencyCast;
use App\Billing\IntervalCast;
use App\Database\Model;
use App\Database\Traits\HasLookupKey;
use App\Models\Billing\Charge;
use App\Models\Billing\Schedule;
use App\Models\Catalog\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Management\Organization;
use App\Models\Values\PlanStatus;
use App\Models\Values\PlanType;
use App\Store\IsPurchasable;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property Organization $organization
 * @property Collection<Charge> $charges
 * @property Collection<Inclusion> $inclusions
 * @property Product $product
 * @property int $organization_id
 * @property int|null $package_id
 * @property PlanType $type
 * @property string $display_name
 * @property string $description
 * @property string $name
 * @property CarbonInterval $renew_interval
 * @property string $billing_anchor // calendar, anniversary
 * @property CarbonInterval $invoice_interval
 * @property \Money\Currency $currency
 * @property string $trial_length
 * @property string $trial_credit
 * @property string $trial_unit
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Plan extends Model implements IsPurchasable
{
    use HasFactory,
        HasLookupKey;

    protected $table = 'pricing_plans';

    protected $casts = [
        'type' => PlanType::class,
        'currency' => CurrencyCast::class,
        'renew_interval' => IntervalCast::class,
        'invoice_interval' => IntervalCast::class,
    ];

    protected $guarded = []; //@todo set fillable fields

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function charges(): HasManyThrough
    {
        return $this
            ->hasManyThrough(Charge::class, Inclusion::class, 'plan_id', 'id', 'id', 'charge_id');
    }

    public function schemes(): BelongsToMany
    {
        return $this
            ->belongsToMany(Scheme::class, 'pricing_packages', 'plan_id', 'pricing_scheme_id');
    }

    public function package(): BelongsTo
    {
        return $this
            ->belongsTo(Package::class);
    }

    public function inclusions(): HasMany
    {
        return $this->hasMany(Inclusion::class)
            ->chaperone();
    }

    public function schedules(): BelongsToMany
    {
        return $this->belongsToMany(Schedule::class, 'billing_schedule_plans');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function displayName()
    {
        return $this->display_name ?? $this->getRouteKey();
    }

    public function isActive(): bool
    {
        return $this->status === PlanStatus::Active->value;
    }

    public function isRecurring(): bool
    {
        return true;
    }

    public function getQuantity(): int
    {
        return 1; // Plans typically have a quantity of 1, but you can modify this if needed
    }

    public function getRenewInterval(): string
    {
        return $this->renew_interval->spec();
    }

    public function getInvoiceInterval(): string
    {
        return $this->invoice_interval?->spec() ?? $this->getRenewInterval();
    }

    public function getPurchasableId(): string
    {
        return $this->getRouteKey();
    }

    public function getDisplayName(): string
    {
        return $this->display_name ?? $this->name ?? $this->getRouteKey();
    }

    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    public function getType(): string
    {
        return 'plan';
    }

    public function getCurrency(): \Money\Currency
    {
        return $this->currency;
    }

    public function getCharges(): \Illuminate\Support\Collection
    {
        return $this->charges;
    }

    public function getProductFamilies(): \Illuminate\Support\Collection
    {
        return $this
            ->inclusions()
            ->where('product_id', '!=', null)
            ->where('feature_id', null)
            ->with(['product', 'product.productFamily'])
            ->get()
            ->map(fn (Inclusion $inclusion) => $inclusion->product->productFamily);
    }

    public function getProductLookupKey(): string
    {
        return $this
            ->inclusions()
            ->where('product_id', '!=', null)
            ->where('feature_id', null)
            ->with(['product', 'product.productFamily'])
            ->get()
            ->map(fn (Inclusion $inclusion) => $inclusion->product->lookup_key)
            ->first()
            ?? '';
    }
}
