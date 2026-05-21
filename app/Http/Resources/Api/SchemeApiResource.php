<?php

namespace App\Http\Resources\Api;

use App\Billing\Currency;
use App\Billing\ISO4217;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * @property Collection<Plan> $plans
 */
class SchemeApiResource extends JsonResource
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
        return [
            'id' => $this->getRouteKey(),
            'object' => 'pricing_scheme',
            'internal_id' => $this->when(! app()->isProduction(), fn () => $this->id),

            'name' => $this->name,
            'version_name' => $this->version_name,
            'version_number' => $this->version_number,

            'plans_count' => $this->whenLoaded('plans', fn () => $this->plans->count(), null),

            'plans' => $this->whenLoaded('plans', fn () => [
                'data' => PlanApiResource::collection($this->plans),
                'object' => 'list',
            ]),

            'packages' => $this->whenLoaded('packages', function () {
                return [
                    'object' => 'list',
                    'data' => $this->packages->map(function (Package $package) {
                        return [
                            'id' => $package->getRouteKey(),
                            'name' => $package->name,
                            'intervals' => $this->getIntervals($package->plans),
                            'currencies' => $this->getCurrencies($package->plans),
                            'plans' => PlanApiResource::collection($package->plans),
                        ];
                    }),
                ];
            }),

            'intervals' => $this->whenLoaded('plans', fn () => $this->getIntervals($this->plans), []),
            'currencies' => $this->whenLoaded('plans', fn () => $this->getCurrencies($this->plans), []),

            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,
            'generated_at' => $this->generated_at?->timestamp,
        ];
    }

    protected function getIntervals(Collection $plans): Collection
    {
        return $plans
            ->pluck('renew_interval')
            ->unique(fn (CarbonInterval $a) => $a->spec())
            ->map(fn ($spec) => CarbonInterval::create($spec))
            ->map(function (CarbonInterval $interval) {
                return [
                    'value' => $interval->spec(),
                    'label' => Arr::get(self::INTERVAL_LOOKUP, $interval->spec(), $interval->spec())[0],
                    'priceSuffix' => Arr::get(self::INTERVAL_LOOKUP, $interval->spec(), $interval->spec())[1],
                ];
            })
            ->values();
    }

    private function getCurrencies(Collection $plans): Collection
    {
        return $plans
            ->pluck('currency')
            ->unique(fn (\Money\Currency $currency) => $currency->getCode())
            ->filter()
            ->map(function (\Money\Currency $currency) {
                $currency = ISO4217::make($currency);

                return [
                    'currency_code' => $currency->getAlpha3(),
                    'currency_symbol' => $currency->getSymbol(),
                    'currency_flag' => $currency->getFlag(),
                ];
            })
            ->values();
    }
}
