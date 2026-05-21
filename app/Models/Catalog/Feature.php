<?php

namespace App\Models\Catalog;

use App\Database\Model;
use App\Database\Traits\HasLookupKey;
use App\Models\Management\Organization;
use App\Models\Usage\Metric;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Feature is an aspect of a Product which can be enabled or disabled.
 * eg: A toggle feature for Product could be "Can send emails".
 * or: A count feature of a Product could be "User count (Unlimited)".
 *
 * @property Organization        $organization
 * @property FeatureSet          $featureSet
 * @property Collection<Product> $products
 *
 * @property int          $id
 * @property int $organization_id
 * @property int $feature_set_id
 * @property string $key
 * @property string $name
 * @property string $description
 * @property Carbon $released_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Feature extends Model
{
    use HasFactory,
        HasLookupKey;

    protected $table = 'catalog_features';

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'catalog_product_features');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(Metric::class);
    }

    public function featureSet(): BelongsTo
    {
        return $this->belongsTo(FeatureSet::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
