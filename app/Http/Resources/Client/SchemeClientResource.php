<?php

namespace App\Http\Resources\Client;

use App\Models\Pricing\Plan;
use App\Models\Values\PlanStatus;
use App\Models\Values\PlanType;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property \Illuminate\Database\Eloquent\Collection<Plan> $plans
 */
class SchemeClientResource extends JsonResource
{
    public static $wrap = false;

    const INTERVAL_LOOKUP = [
        'P1M' => ['Monthly', '/month'],
        'P1Y' => ['Yearly', '/year'],
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $plans = $this->getStandardPlans();

        $addons = $this->plans
            ->where('type', PlanType::addon)
            ->where('status', PlanStatus::Active->value);

        return [
            'id' => $this->getRouteKey(),
            'object' => 'scheme',
            'name' => $this->name,
            'intervals' => $this->whenLoaded('plans', fn () => $this->getIntervals()),
            'plans' => [
                'type' => 'list',
                'data' => PlanClientResource::collection($plans),
            ],
            'addons' => [
                'type' => 'list',
                'data' => PlanClientResource::collection($addons),
            ],
            'created_at' => \Carbon\Carbon::now(),
        ];
    }

    private function getStandardPlans()
    {
        return $this->plans->where('type', PlanType::standard)->where('status', PlanStatus::Active->value);
    }

    protected function getIntervals()
    {
        $plans = $this->getStandardPlans();

        return $plans
            ->pluck('renew_interval')
            ->unique(function (CarbonInterval $interval) {
                return $interval->spec();
            })
            ->map(function (CarbonInterval $interval) {
                return [
                    'value' => $interval->spec(),
                    'label' => Arr::get(self::INTERVAL_LOOKUP, $interval->spec(), $interval->spec())[0],
                    'priceSuffix' => Arr::get(self::INTERVAL_LOOKUP, $interval->spec(), $interval->spec())[1],
                ];
            })
            ->toArray();

    }
}
