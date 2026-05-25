<?php

namespace App\Http\Controllers\API\Actions;

use App\Client\ClientAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Convert\CheckoutState;
use App\Models\Store\Purchase;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateClientCheckoutAction extends Controller
{
    /**
     * The only checkout fields the client SDK is allowed to PATCH directly.
     * Everything else — payment-method association, line-item changes,
     * finalisation, etc. — must go through the typed `/mutations` endpoint
     * so it can be validated against domain rules.
     */
    private const CLIENT_TRANSITIONABLE_STATES = [
        CheckoutState::STARTED->value,
        CheckoutState::ABANDONED->value,
    ];

    public function __invoke(Purchase $purchase, ClientAuthorization $clientJwt, Request $request)
    {
        $agent = $clientJwt->agent;

        abort_if(! $agent, 403, 'Invalid token');

        abort_unless(
            (int) $purchase->organization_id === (int) $clientJwt->client?->organization_id,
            404,
        );

        $validated = $request->validate([
            'current_state' => ['required', Rule::in(self::CLIENT_TRANSITIONABLE_STATES)],
        ]);

        $purchase->current_state = $validated['current_state'];
        $purchase->save();

        return response()->json($purchase);
    }
}
