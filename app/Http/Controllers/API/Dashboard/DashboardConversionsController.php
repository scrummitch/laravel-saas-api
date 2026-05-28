<?php

namespace App\Http\Controllers\API\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CustomerApiResource;
use App\Models\Store\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardConversionsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $organizationId = $request->user()->currentOrganization->id;

        $purchases = Purchase::query()
            ->where('organization_id', $organizationId)
            ->where('current_state', 'completed')
            ->latest('created_at')
            ->with(['customer.twin.connector'])
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $purchases->map(fn (Purchase $purchase) => [
                'id' => $purchase->getRouteKey(),
                'created_at' => $purchase->created_at,
                'customer_id' => $purchase->customer_id,
                'customer' => $purchase->customer
                    ? new CustomerApiResource($purchase->customer)
                    : null,
            ]),
        ]);
    }
}
