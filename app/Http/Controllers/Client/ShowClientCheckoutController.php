<?php

namespace App\Http\Controllers\Client;

use App\Client\ClientAuthorization;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\CheckoutClientResource;
use App\Models\Store\Purchase;

class ShowClientCheckoutController extends Controller
{
    public function __invoke(Purchase $purchase, ClientAuthorization $auth): CheckoutClientResource
    {
        abort_unless(
            (int) $purchase->organization_id === (int) $auth->client?->organization_id,
            404,
        );

        return new CheckoutClientResource($purchase);
    }
}
