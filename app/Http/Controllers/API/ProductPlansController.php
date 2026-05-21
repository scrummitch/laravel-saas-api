<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PlanApiResource;
use App\Models\Catalog\Product;

class ProductPlansController extends Controller
{
    public function index(Product $product)
    {
        $product->loadMissing(['plans', 'plans.charges']);

        return PlanApiResource::collection($product->plans);
    }
}
