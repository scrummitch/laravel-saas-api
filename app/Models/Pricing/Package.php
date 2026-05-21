<?php

namespace App\Models\Pricing;

use App\Database\Model;
use App\Database\Traits\HasLookupKey;
use App\Models\Catalog\Product;
use App\Models\Management\Organization;
use App\Store\IsPurchasable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property Scheme $scheme
 * @property Organization $organization
 *
 * @property int    $pricing_scheme_id
 * @property int    $organization_id
 * @property string $name
 * @property string $lookup_key
 */
class Package extends Model implements IsPurchasable
{
    use HasFactory,
        HasLookupKey;

    protected $table = 'pricing_packages';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class, 'pricing_scheme_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class, 'package_id');
    }

    #[\Override] public function getPurchasableId(): string
    {
        return $this->getRouteKey();
    }

    #[\Override] public function getDisplayName(): string
    {
        return $this->display_name ?? $this->name;
    }

    #[\Override] public function getDescription(): string
    {
        return $this->name;
    }

    #[\Override] public function getType(): string
    {
        return 'package';
    }

    #[\Override] public function getCurrency(): \Money\Currency
    {
        return $this->plans()->first()->currency;
    }

    #[\Override] public function getCharges(): Collection
    {
        return Collection::make();
    }

    #[\Override] public function getProductFamilies(): Collection
    {
        return $this
            ->plans()
            ->first()
            ?->inclusions()
            ->where('product_id', '!=', null)
            ->where('feature_id', null)
            ->with(['product', 'product.productFamily'])
            ->pluck('product.productFamily');
    }

    #[\Override] public function getQuantity(): int
    {
        return 1;
    }

    #[\Override] public function isRecurring(): bool
    {
        return true;
    }
}
