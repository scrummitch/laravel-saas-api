<?php

namespace App\Http\Controllers\API\Intel;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ScenarioApiResource;
use App\Models\Convert\Element;
use App\Models\Intelligence\Scenario;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use App\Rules\ValidCheckoutConfig;
use App\Rules\ValidConditionSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ScenariosController extends Controller
{
    public function show(Scenario $scenario)
    {
        $scenario->loadMissing([
            'element',
            'flow',
            'bundleItems',
            'bundleItems.purchasable',
            'scheme',
        ]);

        return new ScenarioApiResource($scenario);
    }

    public function update(Scenario $scenario, Request $request)
    {
        $request->merge([
            'scheme_id' => Scheme::retrieve($request->get('scheme_id'))?->id,
        ]);

        $validated = $request->validate([
            'display_name' => [
                'required',
            ],
            'intent' => [
                'required',
                Rule::in([
                    'upgrade',
                    'expansion',
                    'addon',
                    'purchase',
                ]),
            ],
            'scheme_id' => [
                'required',
                Rule::exists('pricing_schemes', 'id')
                    ->where('organization_id', $request->user()->organization_id),
            ],
            'lookup_key' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('convert_paywalls', 'variant_key')
//                    ->ignore($this->route('paywall')->id)
                    ->where('organization_id', $request->user()->organization_id),
            ],
            'conditions' => [
                'array',
                new ValidConditionSchema,
            ],
            'element_id' => [
                'required',
//                Rule::exists('convert_elements')
            ],
            'purchasables' => [
                'array',
                new ValidCheckoutConfig,
            ],
        ]);

        $scenario->display_name = $validated['display_name'];
        $scenario->intent = $validated['intent'];
        $scenario->lookup_key = $validated['lookup_key'];
//        $scenario->mode = $validated['mode'];
        $scenario->element_id = Element::retrieve($validated['element_id'])?->id;
        $scenario->conditions = $validated['conditions'];

        $scenario->scheme_id = $validated['scheme_id'];

        // get
        $scenario->properties = Arr::get($validated, 'properties');

        // start a flow from frontend, but load in the arguments to it?
        // show comparison (with scheme)
        // show popover -> comparison -> paywall?

        $scenario->save();

        $scenario->bundleItems()->delete();

        // todo:
        foreach (Arr::get($validated, 'purchasables') ?? [] as $li) {

            $item = match(Arr::get($li, 'object')) {
                'plan' => [
                    'purchasable_type' => 'plan',
                    'purchasable_id' => Plan::retrieve(Arr::get($li, 'id'))?->id,
                ],
                'package' => [
                    'purchasable_type' => 'package',
                    'purchasable_id' => Package::retrieve(Arr::get($li, 'id'))?->id,
                ],
                default => [],
            };

            $scenario->bundleItems()->create($item);
        }
        return new ScenarioApiResource($scenario);
    }
}
