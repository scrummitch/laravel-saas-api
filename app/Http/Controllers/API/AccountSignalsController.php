<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SignalApiResource;
use App\Models\Account\Signal;
use Illuminate\Http\Request;

class AccountSignalsController extends Controller
{
    public function index(Request $request)
    {
        $org = $request->user()->currentOrganization;

        $query = Signal::query()
            ->select('intel_signals.*')
            ->join('account_customers', 'account_customers.id', '=', 'intel_signals.customer_id')
            ->where('account_customers.organization_id', $org->id)
            ->with(['customer'])
            ->latest('id');

        return SignalApiResource::collection($query->simplePaginate());
    }
}
