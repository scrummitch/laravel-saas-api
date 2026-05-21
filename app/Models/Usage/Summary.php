<?php

namespace App\Models\Usage;

use App\Database\Model;

/**
 * @property int $organization_id
 * @property int $metric_id
 * @property int $customer_id
 * @property int $latest_event_id
 * @property string $event_name
 * @property int $current_aggregation
 */
class Summary extends Model
{
    protected $table = 'usage_summaries';

    protected $casts = [
        'current_aggregation' => 'integer',
    ];

    public function metric()
    {
        return $this->belongsTo(Metric::class);
    }
}
