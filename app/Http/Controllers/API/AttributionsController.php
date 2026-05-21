<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Convert\Attribution;
use Illuminate\Http\Request;

class AttributionsController extends Controller
{
    public function index()
    {
        $organization = auth()->user()->currentOrganization;

        return [
            'data' => Attribution::query()
                ->select('convert_attributions.*')
                ->join('convert_paywall_sessions', 'convert_attributions.session_id', '=', 'convert_paywall_sessions.id')
                ->join('convert_paywalls', 'convert_paywall_sessions.paywall_id', '=', 'convert_paywalls.id')
                ->join('convert_workflows', 'convert_paywalls.workflow_id', '=', 'convert_workflows.id')
                ->where('convert_workflows.organization_id', $organization->id)
                ->with(['session', 'session.customer', 'producer', 'producer.workflow', 'purchase', 'session.collector', 'session.collector.client'])
                ->latest()
                ->get()
                ->map(function (Attribution $d) {
                    return [
                        'id' => $d->id,
                        'paywall' => [
                            'name' => $d->producer?->name,
                        ],
                        'environment' => $d->session->collector?->client?->environment ?? 'unknown',
                        'customer' => [
                            'reference_id' => $d->session->customer->reference_id,
                        ],
                        'workflow_id' => $d->producer->workflow->getRouteKey(),
                        'proceeds_amount_gross' => $d->proceeds_amount_gross,
                        'proceeds_amount_net' => $d->proceeds_amount_net,
                        'created_at' => $d->created_at,
                        'data' => $d->data,
                        'purchase' => $d->purchase?->toArray(),
                    ];
                })
        ];
    }

    public function update(Attribution $attribution, Request $request)
    {
        $validated = $request->validate([
            'proceeds_amount_gross' => [
                'numeric',
            ],
        ]);

        if ($request->filled('proceeds_amount_gross')) {
            $attribution->proceeds_amount_gross = $validated['proceeds_amount_gross'];
        }

        $attribution->save();

        return $attribution;
    }
}
