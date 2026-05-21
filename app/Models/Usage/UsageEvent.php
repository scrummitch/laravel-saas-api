<?php

namespace App\Models\Usage;

use App\Database\Model;
use App\Models\Account\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * @property int $organization_id
 * @property int $client_id
 * @property int $customer_id
 * @property int $agent_id
 * @property string $event_name
 * @property string $unique_id
 * @property array $properties
 * @property array $metadata
 */
class UsageEvent extends Model
{
    protected $table = 'usage_events';

    public function casts()
    {
        return [
            'properties' => 'json',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function generateSummary()
    {
        $metrics = Metric::query()
            ->where('event_name', $this->event_name)
            ->where('organization_id', $this->organization_id)
            ->get();

        /* @var Metric $metric */
        foreach ($metrics as $metric) {
            /* @var Summary $summary */
            $summary = Summary::query()
                ->where([
                    'organization_id' => $this->organization_id,
                    'customer_id' => $this->customer_id,
                    'metric_id' => $metric->id,
                ])
                ->firstOrNew();

            $summary->organization_id = $this->organization_id;
            $summary->metric_id = $metric?->id;
            $summary->customer_id = $this->customer_id;
            $summary->event_name = $this->event_name;
            $summary->latest_event_id = $this->id;
            $summary->current_aggregation = match($metric->aggregation) {
                AggregationValue::Latest => $this->calculateLatestValue($metric),
                AggregationValue::Sum => $this->calculateSumValue($metric),
                AggregationValue::Count => $this->calculateCountValue($metric),
                default => 1
            };

            $summary->save();
        }

        return $summary;
    }

    public function getRouteKeyName(): string
    {
        return 'unique_id';
    }

    public function calculateLatestValue(Metric $metric)
    {
        $fieldName = $metric->field_name ?? 'quantity';

        return Arr::get($this->properties, $fieldName, 1);
    }

    private function calculateSumValue(?Metric $metric)
    {
        $fieldName = $metric->field_name ?? 'quantity';

        return DB::table('usage_events')
            ->where('organization_id', $this->organization_id)
            ->where('customer_id', $this->customer_id)
            ->where('event_name', $this->event_name)
            ->sum('properties->' . $fieldName);
    }

    private function calculateCountValue(?Metric $metric)
    {
        return DB::table('usage_events')
            ->where('organization_id', $this->organization_id)
            ->where('customer_id', $this->customer_id)
            ->where('event_name', $this->event_name)
            ->count();
    }
}
