<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Account\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;

class AgentsController extends Controller
{
    public function index(Request $request): Paginator
    {
        return Agent::query()
            ->where('organization_id', $request->user()->currentOrganization->id)
            ->with(['associations.customer'])
            ->latest()
            ->simplePaginate();
    }

    public function destroy(Agent $agent): Response
    {
        Gate::authorize('delete', $agent);

        $agent->deleteOrFail();

        return response()->noContent();
    }
}
