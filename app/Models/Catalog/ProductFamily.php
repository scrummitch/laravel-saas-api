<?php

namespace App\Models\Catalog;

use App\Database\Model;
use App\Database\Traits\HasLookupKey;
use App\Models\HasAttachments;
use App\Models\Management\Organization;
use App\Models\Media\Asset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * @property Organization $organization
 * @property ?Asset $icon
 */
class ProductFamily extends Model
{
    use HasAttachments,
        HasFactory,
        HasLookupKey;

    protected $table = 'catalog_product_families';

    public function icon(): HasOneThrough
    {
        return $this->singleAttachment('icon');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
