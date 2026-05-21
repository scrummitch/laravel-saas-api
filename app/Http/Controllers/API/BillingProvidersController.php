<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BillingProviderResource;
use App\Models\Billing\BillingProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingProvidersController extends Controller
{
    public function index(Request $request)
    {
        $billingProvidersQuery = $request
            ->user()
            ->currentOrganization->billingProviders();

        $billingProvidersQuery->with([
            'operations',
        ]);

        return BillingProviderResource::collection($billingProvidersQuery->paginate());
    }

    public function show(BillingProvider $provider, Request $request)
    {
        return new BillingProviderResource($provider);
    }

    public function storeOperation(BillingProvider $provider, Request $request)
    {
        $validated = $request->validate([
            'type' => [
                'required',
                Rule::in(['import']),
            ],
        ]);

        if ($validated['type'] === 'import') {
            $provider->import();
        }

        $provider->refresh();

        return new BillingProviderResource($provider);
    }
}
