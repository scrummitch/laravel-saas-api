<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\Api\ProductFeatureResource;
use App\Models\Catalog\Feature;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductFeature;
use App\Models\Pricing\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductFeaturesController
{
    public function index(Product $product)
    {
        $query = $product
            ->productFeatures()
            ->with(['feature']);

        return ProductFeatureResource::collection($query->paginate());
    }

    public function store(Product $product, Request $request)
    {
        $feature = Feature::retrieve($request->input('feature'));

        $request->merge([
            'feature_id' => $feature?->id,
        ]);

        $validated = $request->validate([
            'feature_id' => [
                'required',
                Rule::exists(Feature::class, 'id')
                    ->where('organization_id', $product->organization_id),
            ],
            ...Arr::only($this->rules(), ['label', 'description', 'note', 'allowance', 'unit', 'reset_period'])
        ]);

        $productFeature = new ProductFeature;
        $productFeature->fill(Arr::only($validated, ['label', 'description', 'note']));

        $productFeature->feature_id = Arr::get($validated, 'feature_id');
        $productFeature->allowance = $validated['allowance'];
        $productFeature->unit = $validated['unit'];
        $productFeature->reset_period = $validated['reset_period'];

        $productFeature->product()->associate($product);
        $productFeature->save();


        $planResults = DB::table('pricing_plans')
            ->join('catalog_inclusions', 'catalog_inclusions.plan_id', '=', 'pricing_plans.id')
            ->where('catalog_inclusions.product_id', $product->id)
            ->select('pricing_plans.id as plan_id', 'catalog_inclusions.*')
            ->get();

        $productFeature->refresh();

//        collect($planResults)
//            ->unique('plan_id')
//            ->each(function ($planResult) use ($productFeature) {
//                DB::table('catalog_inclusions')
//                    ->upsert([
//                        'plan_id' => $planResult->plan_id,
//                        'product_id' => $productFeature->product_id,
//                        'feature_id' => null,
//                        'created_at' => now(),
//                    ], ['plan_id', 'product_id', 'feature_id'], [
//                        'updated_at' => now(),
//                    ]);
//            });

        $inserts = collect($planResults)

            ->map(function ($planResult) use ($productFeature) {
                return [
                    'plan_id' => $planResult->plan_id,
                    'product_id' => $productFeature->product_id,
                    'feature_id' => $productFeature->feature_id,
                    // needs to be on metric side!
//                    'metric_id' => $productFeature->feature->metric_id,
                    'default_limit' => $productFeature->allowance,
                    'limit_unit' => $productFeature->unit,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'ulid' => Str::ulid(),
                ];
            })
            ->toArray();

        DB::table('catalog_inclusions')
            ->upsert($inserts, ['plan_id', 'product_id', 'feature_id']);


        return new ProductFeatureResource($productFeature);
    }

    public function update(Product $product, Feature $feature, Request $request)
    {
        /* @var ProductFeature $productFeature */
        $productFeature = $product
            ->productFeatures()
            ->where('feature_id', $feature->id)
            ->first();

        $request->merge([
            'feature_id' => $productFeature->feature_id,
        ]);

        $validated = $request->validate($this->rules());

        $productFeature->allowance = Arr::get($validated, 'allowance');
        $productFeature->unit = Arr::get($validated, 'unit');
        $productFeature->reset_period = Arr::get($validated, 'reset_period');

        $productFeature->save();

        $productFeature->refresh();

        return new ProductFeatureResource($productFeature);
    }

    public function destroy(Product $product, $id)
    {
        // join via features table on id to delete
        $del = $product
            ->productFeatures()
            ->join('catalog_features', 'catalog_features.id', '=', 'catalog_product_features.feature_id')
            ->where('catalog_features.lookup_key', $id)
            ->delete();

        return response([
            'del' => $del,
        ]);
    }

    private function rules()
    {
        return [
            'feature_id' => [
                'required',
                Rule::exists(Feature::class, 'id'),
            ],
            'label' => [
                'nullable',
                'string',
                'max:255',
                'min:4',
            ],
            'description' => [
                'nullable',
                'max:255',
                'min:4',
            ],
            'note' => [
                'nullable',
                'max:255',
                'min:4',
            ],
            'allowance' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'unit' => [
                'nullable',
                'string',
                'max:255',
                'min:4',
            ],
            'reset_period' => [
                'nullable',
                'string',
                Rule::in([
                    'calendar',
                    'anniversary',
                    'persistent',
                ]),
            ],
        ];
    }
}
