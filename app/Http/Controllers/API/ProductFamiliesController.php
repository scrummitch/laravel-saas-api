<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductFamilyRequest;
use App\Http\Requests\UpdateProductFamilyRequest;
use App\Http\Resources\Api\ProductFamilyApiResource;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductFamily;
use Illuminate\Http\Request;

class ProductFamiliesController extends Controller
{
    public function index(Request $request)
    {
        $families = $request
            ->user()
            ->currentOrganization
            ->productFamilies()
            ->with(['icon']);

        return ProductFamilyApiResource::collection($families->paginate());
    }

    public function show(ProductFamily $productFamily)
    {
        $productFamily->loadMissing(['products']);

        return new ProductFamilyApiResource($productFamily);
    }

    public function store(StoreProductFamilyRequest $request)
    {
        $org = $request->user()->currentOrganization;
        $asset = \App\Models\Media\Asset::retrieve($request['asset_id']);
        $productFamily = new ProductFamily;
        $productFamily->organization_id = $org->id;
        $productFamily->name = $request['name'];
        $productFamily->lookup_key = $request['lookup_key'];

        $productFamily->save();

        $productIds = $request['selected_product_ids'];
        if (count($productIds) > 0) {
            Product::query()
                ->whereIn('lookup_key', $productIds)
                ->where('organization_id', $org->id)
                ->update(['product_family_id' => $productFamily->id]);
        }

        if ($asset) {
            $productFamily->attachAsset($asset, 'icon');
        }

        return new ProductFamilyApiResource($productFamily);
    }

    public function update(UpdateProductFamilyRequest $request, ProductFamily $productFamily)
    {
        $org = $request->user()->currentOrganization;
        $productFamily->loadMissing(['products']);

        $asset = \App\Models\Media\Asset::retrieve($request['asset_id']);
        $productFamily->name = $request['name'];
        $productFamily->lookup_key = $request['lookup_key'];

        $productIds = $request['selected_product_ids'];
        if (count($productIds) > 0) {
            $currentProductIds = $productFamily->products->pluck('lookup_key')->toArray();
            Product::query()
                ->whereIn('lookup_key', $productIds)
                ->where('organization_id', $org->id)
                ->update(['product_family_id' => $productFamily->id]);

            // remove
            $unlinkProductIds = array_diff($currentProductIds, $productIds);
            if (count($unlinkProductIds) > 0) {
                Product::query()
                    ->whereIn('lookup_key', $unlinkProductIds)
                    ->where('organization_id', $org->id)
                    ->update(['product_family_id' => null]);
            }
        }

        $productFamily->save();

        if ($asset) {
            $productFamily->attachAsset($asset, 'icon');
        }

        return new ProductFamilyApiResource($productFamily);
    }
}
