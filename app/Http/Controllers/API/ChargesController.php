<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChargeRequest;
use App\Http\Requests\UpdateChargeRequest;
use App\Http\Resources\Api\ChargeApiResource;
use App\Models\Billing\Charge;
use App\Models\Catalog\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ChargesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $charges = Charge::query()
            ->where('organization_id', $request->user()->currentOrganization->id)
            ->with(['product', 'twins', 'twins.connector', 'inclusions', 'inclusions.product', 'inclusions.metric', 'inclusions.feature', 'inclusions.plan'])
            ->latest('id');

        if ($request->filled('filter.product')
            && $product = Product::retrieve($request->input('filter.product'))
        ) {
            $charges->where('product_id', $product->id);
        }

        return ChargeApiResource::collection($charges->simplePaginate(100));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreChargeRequest $request)
    {
        $charge = new Charge();
        $fields = $request->only(['amount', 'mode', 'name', 'type', 'currency', 'minimum_billable_usage', 'amount_minimum_spend']);

        $charge->fill($fields);
        $charge->organization_id = $request->user()->currentOrganization->id;

        if ($request->input('product') !== null){
            $product = Product::retrieve($request->input('product'));
            $charge->product()->associate($product);
        }

        $charge->save();

        return new ChargeApiResource($charge);
    }

    /**
     * Display the specified resource.
     */
    public function show(Charge $charge)
    {
        return new ChargeApiResource($charge);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateChargeRequest $request, Charge $charge)
    {
        $fields = $request->validated();

        $charge->fill(Arr::only($fields, ['name', 'amount', 'mode', 'type', 'currency', 'minimum_billable_usage', 'amount_minimum_spend']));
        $charge->organization_id = $request->user()->currentOrganization->id;

        if ($request->filled('product')) {
            $charge->product()->associate($request->input('product'));
        }

        $charge->save();

        return new ChargeApiResource($charge);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Charge $charge)
    {
        //
    }
}
