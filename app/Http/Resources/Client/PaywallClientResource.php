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
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stages = $this->when(env('CUSTOM_STAGES'), function () {
            return json_decode(Storage::get(env('CUSTOM_STAGES')), true);
        }, $this->element?->view);

        $layouts = collect($stages)
            ->flatMap(function ($stage) {
                return array_values(Arr::get($stage, 'layout', []));
            })
            ->merge([
                'split-checkout@v1',
            ])
            ->unique()
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
