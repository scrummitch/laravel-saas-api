<?php

namespace App\Http\Controllers\API\Actions;

use App\Client\ClientAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Store\Purchase;
use Illuminate\Http\Request;

class UpdateClientCheckoutAction extends Controller
{
    public function __invoke(Client $client, Purchase $checkout, ClientAuthorization $clientJwt, Request $request)
    {
        $agent = $clientJwt->agent;

        abort_if(! $agent, 403, 'Invalid token');

        foreach ($request->all() as $key => $value) {
            $checkout->{$key} = $value;
        }
        $checkout->save();

        return response()->json($checkout);
    }
}
