<?php

namespace App\Models\Billing;

use App\Billing\ChargeFormatter;
use App\Billing\Charges\GraduatedCharge;
use App\Billing\Charges\PackageCharge;
use App\Billing\Charges\StandardCharge;
use App\Billing\Charges\VolumeCharge;
use App\Billing\CurrencyCast;
use App\Billing\ISO4217;
use App\Billing\MoneyCast;
use App\Database\Model;
use App\Models\Catalog\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductFamily;
use App\Models\Management\Organization;
use App\Models\Pricing\Plan;
use App\Models\Twin;
use App\Models\Usage\Metric;
use App\Store\IsPurchasable;
use Database\Factories\Billing\ChargeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Money\Currency;
use Money\Money;
use Parental\HasChildren;
use Stripe\Price;

/**
 * Charge is a pricing object which is used to calculate the cost of a Product for a Customer.
 *
 * @module Billing
 *
 * @property Organization $organization
 * @property Product $product The Product for which this Charge provides pricing

 * @property int $organization_id
 * @property int $product_id
 *
 * @property string   $name The customer-facing name of this Charge
 * @property Currency $currency The currency in which this Charge is expressed
 * @property Money    $amount
 * @property string   $mode in_advance|in_arrears
 * @property string   $type one_time|standard|graduated|volume|packages todo: seats
 * @property Twin     $twin
 * @property array        $properties
 * @property Money|null   $amount_minimum_spend
 * @property int|null     $minimum_billable_usage
 * @property string|null  $invoice_description
 *
 * // properties needs to be better than this shxt
 */
class Charge extends Model implements IsPurchasable
{
    use HasFactory,
        HasChildren,
        SoftDeletes;

    const MAX_QUANTITY = 1_000_000_000_000; // 1 trillion

    protected $table = 'billing_charges';

    protected $fillable = ['product_id','type', 'currency', 'amount', 'properties', 'mode', 'name', 'amount_minimum_spend', 'minimum_billable_usage'];

    protected $childTypes = [
        'graduated' => GraduatedCharge::class,
        'standard' => StandardCharge::class,
        'volume' => VolumeCharge::class,
        'package' => PackageCharge::class,
    ];

    protected $casts = [
        'currency' => CurrencyCast::class,
        'amount' => MoneyCast::class,
        'properties' => 'json',
        'amount_minimum_spend' => MoneyCast::class,
        'minimum_billable_usage' => 'int',
    ];

    protected static function newFactory()
    {
        return new ChargeFactory();
    }

    public function calculateAmount(float $quantity = 1.0): Money
    {
        return $this->amount->multiply($quantity);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'catalog_inclusions');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inclusions(): HasMany
    {
        return $this->hasMany(Inclusion::class);
    }

    public function amountFormatted(): string
    {
        return ChargeFormatter::format($this->amount);
    }

    public function getAmountAttribute()
    {
        $amount = (int) $this->attributes['amount'] > 0 ? $this->attributes['amount'] : $this->getTieredFlatAmount();

        return new Money($amount, new Currency($this->attributes['currency']));
    }

    public function createLink(Twin $twin)
    {
        $twin->link($this);
    }

    public function updateFromPrice(Price $price)
    {
        $this->name = $price->lookup_key ?? $price->id;
        $this->currency = $price->currency;
        $this->amount = $price->unit_amount;

        $this->save();
    }

    public function twins(): MorphMany
    {
        return $this->morphMany(Twin::class, 'linkable');
    }

    public function fillFromTwin(Twin $twin): self
    {
        /* @var Price $price */
        $price = $twin->object();

        $this->currency = new Currency($price->currency);
        $this->name = $price->lookup_key ?? $price->id;
        $type = 'standard';

        if ($price->billing_scheme === 'per_unit') {
            $this->amount = new Money($price->unit_amount, new Currency($price->currency));
        }

        if ($price->billing_scheme === 'tiered') {
            $tierTypes = [
                'graduated' => 'graduated',
                'volume' => 'volume',
            ];
            // one_time|standard|graduated|volume
            $this->amount = null;
            $type = Arr::get($tierTypes, $price->tiers_mode, 'graduated');
            $this->properties = $this->convertTiersToProperties($price->tiers ?? [], $price->tiers_mode);
        }

        if (is_null($this->type)) {
            $this->type = $type;
        }

        $this->mode = 'in_advance';

        return $this;
    }

    protected function convertTiersToProperties(array $tiers, string $mode): array
    {
        $properties = [];

        foreach ($tiers as $tier) {
            $properties[] = [
                'flat_amount' => $tier->flat_amount_decimal,
                'unit_amount' => $tier->unit_amount_decimal,
                'up_to' => $tier->up_to,
            ];
        }

        return $properties;
    }

    private function calculateMinimumAmount(Price $price)
    {
        if (! is_null($price->unit_amount)) {
            return $price->unit_amount;
        }

        if (! isset($price->recurring)) {
            return $price->unit_amount;
        }

        if ($price->tiers_mode === 'graduated' && ! empty($price->tiers)) {
            if (data_get($price, 'tiers.0.flat_amount')) {
                return $price->tiers[0]->flat_amount;
            }

            if ($price->tiers[0]->unit_amount) {
                return $price->tiers[0]->unit_amount;
            }
        }

        return 0;
    }

    public function isFree(): bool
    {
        return $this->amount?->isZero();
    }

    private function getTieredFlatAmount(): ?int
    {
        $base = collect($this->properties)->firstWhere('flat_amount', '!=', null);

        if ($base) {
            return $base['flat_amount'];
        }

        return 0;
    }

    public function getPurchasableId(): string
    {
        return $this->getRouteKey();
    }

    public function getDisplayName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    public function getType(): string
    {
        return 'charge';
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getCharges(): Collection
    {
        return collect([$this]);
    }

    public function getProductFamilies(): Collection
    {
        return Collection::make([$this->product?->productFamily]);
    }

    public function getQuantity(): int
    {
        return 1;
    }

    public function isRecurring(): bool
    {
        return false;
    }
}
