<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInclusionRequest;
use App\Http\Requests\UpdateInclusionRequest;
use App\Http\Resources\Api\InclusionApiResource;
use App\Http\Resources\Api\PlanApiResource;
use App\Models\Billing\Charge;
use App\Models\Catalog\Inclusion;
//use App\Models\Pricing\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Pricing\Plan;
use App\Models\Usage\Metric;

class PlanInclusionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Plan $plan)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Plan $plan, StoreInclusionRequest $request)
    {
        if ($request->filled('product')) {
            // get hte product
            // fill inclusions

            $product = Product::retrieve($request->input('product'));
            $product->loadMissing(['productFeatures', 'productFeatures.feature']);

            $productInclusion = new Inclusion();
            $productInclusion->plan_id = $plan->id;
            $productInclusion->product_id = $product->id;
            $productInclusion->save();

            foreach ($product->productFeatures as $productFeature) {
                // create new inclusions
                $inclusion = new Inclusion();
                $inclusion->plan_id = $plan->id;
                $inclusion->product_id = $product->id;
                $inclusion->feature_id = $productFeature->feature_id;
                $inclusion->default_limit = $productFeature->allowance;
                $inclusion->limit_unit = $productFeature->unit;
                $inclusion->reset_anchor = $productFeature->reset_period;
                $inclusion->save();
            }
        }

        if ($request->filled('charge')) {
            // associate a charge
        }


        $plan->fresh();
        $plan->loadMissing(['inclusions', 'inclusions.product', 'inclusions.feature', 'inclusions.charge', 'inclusions.metric']);

        return new PlanApiResource($plan);
    }

    /**
     * Display the specified resource.
     */
    public function show(Plan $plan, Inclusion $inclusion)
    {
        // update values of inclusions
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Plan $plan, Inclusion $inclusion, UpdateInclusionRequest $request)
    {
        if ($request->input('metric') !== null) {
           $metric = Metric::retrieve($request->input('metric'));

           $inclusion->metric()->associate($metric);
        }

        foreach (['default_limit', 'limit_unit', 'reset_anchor', 'name'] as $field) {
            if ($request->has($field)) {
                $inclusion->{$field} = $request->input($field);
            }
        }

        if($request->input('charge') !== null) {
            $charge = Charge::query()
                ->where('id', $request->input('charge.id'))
                ->where('organization_id', $request->user()->currentOrganization->id)
                ->firstOrFail();

            $inclusion->charge()->associate($charge);
        }

        $inclusion->save();

        return new InclusionApiResource($inclusion);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Plan $plan, Inclusion $inclusion)
    {
        $inclusion->delete();
    }
}
