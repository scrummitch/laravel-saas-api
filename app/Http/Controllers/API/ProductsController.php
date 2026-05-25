<?php

namespace App\Http\Controllers\API;

use App\Http\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\Api\ProductApiResource;
use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    use ResolvesPerPage;

    public function __construct()
    {
        $this->authorizeResource(Product::class, 'product');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $org = auth()->user()->currentOrganization;

        /* @var Builder $query */
        $query = $org
            ->products()
            ->with(['twins', 'twins.connector', 'features', 'productFamily']);

        return ProductApiResource::collection($query->paginate($this->perPage($request)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $product->loadMissing(['twins', 'productFeatures', 'productFeatures.feature']);

        return new ProductApiResource($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        //
    }
}
