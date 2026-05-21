<?php

namespace App\Models\Catalog;

use App\Database\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $product_id
 * @property int $feature_id
 * @property string $name
 * @property string $description
 * @property string $note
 *
 * @property int    $allowance    // max number of units per period
 * @property string $unit         // message|team_member
 * @property string $reset_period // invoice | renewal | calendar
 */
// limit is max number of {units} per {reset_period}
class ProductFeature extends Model
{
    protected $table = 'catalog_product_features';

    protected $fillable = [
        'name',
        'description',
        'note',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
