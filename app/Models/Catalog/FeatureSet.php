<?php

namespace App\Models\Catalog;

use App\Database\Model;
use App\Database\Traits\HasNiceUlids;
use App\Models\Management\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @proprety int $organization_id
 *
 * @property string $module_key
 * @property int $key
 * @property int $name
 * @property int $description
 * @property int $released_at
 */
class FeatureSet extends Model
{
    use HasFactory,
        HasNiceUlids;

    protected $guarded = [];

    protected $table = 'catalog_feature_sets';

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
