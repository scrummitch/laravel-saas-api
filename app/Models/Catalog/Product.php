<?php

namespace App\Models\Catalog;

use App\Database\Model;
use App\Database\Traits\HasVersionedLookupKey;
use App\Models\Billing\Charge;
use App\Models\HasTwins;
use App\Models\Management\Organization;
use App\Models\Pricing\Plan;
use App\Models\Twin;
use App\Models\Values\ProductStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Products are purchasable items which allow access to sets of Features as "ProductFeatures".
 * Prices are set in Plans, which are payment contracts between a Customer and Products.
 * Customers subscribe to products by paying a Charge, which is a price on a Product.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $ancestor_id p
 * @property int|null $successor_id p
 * @property ProductStatus $status ?
 * @property string|null $name s
 * @property string|null $display_name p
 * @property string|null $key s
 * @property string|null $description p
 * @property string|null $product_family_id
 * @property Carbon|null $published_at s
 * @property Carbon|null $archived_at ?
 * @property int $version_number
 * @property string $version_name
 * @property Organization $organization
 * @property Product|null $ancestor
 * @property Product|null $successor
 * @property Product[] $descendents
 * @property ProductFamily|null $productFamily
 * @property string $lookup_key
 * @property Collection<ProductFeature> $productFeatures
 */
class Product extends Model
{
    use HasFactory,
        HasTwins,
        HasVersionedLookupKey;

    protected $table = 'catalog_products';

    public function casts()
    {
        return [
            'status' => ProductStatus::class,
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($product) {
            $product->version_name = Str::start($product->version_id, 'v');
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public static function upsertFromTwin(Twin $twin): static
    {
        /* @var \Stripe\Product $stripeProduct */
        $stripeProduct = $twin->object();

        /* @var Product $product */
        $product = Product::query()
            ->where('organization_id', $twin->organization_id)
            ->where('lookup_key', $twin->reference_id)
            ->firstOrNew();

        $product->organization_id = $twin->organization_id;
        $product->lookup_key = $twin->reference_id;
        $product->name = $stripeProduct->name;
        $product->description = $stripeProduct->description;
        $product->status = $stripeProduct->active ? ProductStatus::active : ProductStatus::archived;

        if (empty($product->display_name)) {
            $product->display_name = $product->name;
        }

        if (empty($product->published_at)) {
            $product->published_at = $twin->reference_created_at;
        }

        $product->version_number = 1;
        $product->version_name = 'v1';

        $product->save();

        return $product;
    }

    public function features(): HasManyThrough
    {
        return $this->hasManyThrough(Feature::class, ProductFeature::class, 'product_id', 'id', 'id', 'feature_id');
    }

    public function productFeatures(): HasMany
    {
        return $this->hasMany(ProductFeature::class);
    }

    public function ancestor(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ancestor_id');
    }

    public function successor(): HasOne
    {
        return $this->hasOne(Product::class, 'successor_id');
    }

    public function descendents(): HasMany
    {
        return $this->hasMany(Product::class, 'ancestor_id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function productFamily(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'product_family_id');
    }

    public function getFamilyAttribute(): ?string
    {
        return $this->productFamily?->lookup_key;
    }
}
