<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

class PaywallClientResource extends JsonResource
{
    /**
     * Paywall layout identifiers that are allowed to be parsed from disk.
     * `$layout` flows from stored element data which an org admin can craft,
     * so without this filter `layout: "../../foo"` would let them read
     * arbitrary `.yml` files under the project root when an end-user
     * requests their paywall.
     */
    private const ALLOWED_LAYOUTS = [
        'split-checkout@v1',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stages = $this->when(config('app.custom_stages'), function () {
            return json_decode(Storage::get(config('app.custom_stages')), true);
        }, $this->element?->view);

        $layouts = collect($stages)
            ->flatMap(function ($stage) {
                return array_values(Arr::get($stage, 'layout', []));
            })
            ->merge([
                'split-checkout@v1',
            ])
            ->unique()
            ->filter(fn ($layout) => is_string($layout) && in_array($layout, self::ALLOWED_LAYOUTS, true))
            ->mapWithKeys(function ($layout) {
                return [$layout => Yaml::parseFile(resource_path('paywall-templates/'.$layout.'.yml'))];
            });

        return [
            'id' => $this->getRouteKey(),
            'object' => 'paywall',
            'name' => $this->display_name,
//            'mode' => $this->mode,
            'intent' => $this->intent,
            'settings' => $this->properties,
            'layouts' => $layouts,
            'stages' => $stages ?? [],
            'conditions' => $this->conditions,
            'updated_at' => $this->updated_at?->timestamp,
        ];
    }
}
