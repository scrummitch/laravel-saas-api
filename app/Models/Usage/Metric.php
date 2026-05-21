<?php

namespace App\Models\Usage;

use App\Database\Model;
use App\Database\Traits\HasNiceUlids;
use App\Models\Account\Customer;
use App\Models\Catalog\Feature;
use App\Models\Management\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Organization $organization
 * @property Feature      $feature
 *
 * @property int     $organization_id
 * @property int     $feature_id
 * @property string $event_name
 * @property AggregationValue $aggregation
 * @property string $type persistent, transient
 * @property string $field_name
 * @property string $weighted_interval
 * @property array $filters
 */
class Metric extends Model
{
    use HasFactory,
        HasNiceUlids;

    protected $table = 'usage_metrics';

    public function casts()
    {
        return [
            'filters' => 'array',
            'aggregation' => AggregationValue::class,
        ];
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customerSummary(Customer $customer): ?Summary
    {
        return Summary::query()
            ->where([
                'customer_id' => $customer->id,
                'metric_id' => $this->id,
            ])
            ->first();
    }
}
