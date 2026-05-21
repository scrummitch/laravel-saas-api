<?php

namespace App\Models\Catalog;

use App\Database\Model;
use App\Database\Traits\HasNiceUlids;
use App\Models\Billing\Charge;
use App\Models\Pricing\Plan;
use App\Models\Usage\Metric;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inclusion is an attribute included in a plan.
 *
 * @property Product $product
 * @property Feature $feature
 * @property Charge  $charge
 * @property Metric  $metric
 *
 * @property int      $plan_id
 * @property int|null $product_id
 * @property int|null $feature_id
 * @property int      $charge_id
 * @property int|null $metric_id
 *
 * @property string|null  $grouping_key
 * @property string|null  $grouping_label
 * @property string|null  $display_name
 * @property string|null  $description
 * @property string|null  $name
 * @property string|null  $note
 * @property double       $default_limit
 * @property string|null  $limit_unit
 * @property string|null  $reset_anchor
 * @property bool         $is_approved
 * @property Carbon       $created_at
 * @property Carbon       $updated_at
 */
class Inclusion extends Model
{
    use HasFactory,
        HasNiceUlids;

    protected $table = 'catalog_inclusions';

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
