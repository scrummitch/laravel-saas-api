<?php

namespace App\Http\Controllers\Clients;

use App\Client\ClientAuthorization;
use App\Convert\DataObjects\WorkflowTrigger;
use App\Convert\PaywallService;
use App\Http\Controllers\Client\CreateCheckoutService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ThemeApiResource;
use App\Http\Resources\Client\CheckoutClientResource;
use App\Http\Resources\Client\PaywallClientResource;
use App\Http\Resources\Client\ScheduleClientResource;
use App\Http\Resources\Client\SchemeClientResource;
use App\Models\Convert\Flow;
use App\Models\Convert\Theme;
use App\Models\Store\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @deprecated
 */
class ClientPaywallController extends Controller
{

    public function __invoke($paywallId, ClientAuthorization $auth, Request $request)
    {
        $scenario = PaywallService::resolveScenario($auth->client->organization, $paywallId);

        $paywalls = new PaywallService(
            scenario: $scenario,
            auth: $auth,
            request: $request
        );

        $activity = $paywalls->getActivity();

        $customer = $auth->customer;

        $customer?->loadMissing([
            'schedule.subscriptions.plan',
            'schedule.subscriptions.plan.package',
            'schedule.subscriptions.plan.charges',
        ]);

        abort_unless($activity, 400, 'unable to find or create activity');

        if ($activity->collector->isNot($auth->collector)) {
            abort(404);
        }

        $theme = Theme::query()
            ->where('organization_id', $auth->client->organization_id)
            ->latest('id')
            ->first();

        $purchase = Purchase::query()
            ->where('activity_id', $activity->id)
            ->first() ?? (new CreateCheckoutService)(
                client: $auth->client,
                scenario: $scenario,
                agent: $auth->agent,
                customer: $auth->customer,
            );

        if (is_null($purchase->activity_id)) {
            $purchase->activity_id = $activity->id;
            $purchase->save();
        }

        return [
            'cart_token' => $purchase->provider_id.'?sig='.hash_hmac('sha256', $purchase->provider_id, config('app.key')),
            'config' => [
                'services' => $purchase->billing_provider ? [
                    'stripe' => [
                        'publishable_key' => Arr::get($purchase->billing_provider->config, 'access_token.stripe_publishable_key'),
                    ],
                ] : null,
            ],
            'checkout' => new CheckoutClientResource($purchase),
            'theme' => $theme ? new ThemeApiResource($theme) : null,
            'customer' => $customer, //@todo create Customer resource
            'schedule' => $customer?->schedule ? new ScheduleClientResource($customer->schedule) : null,
            'scheme' => $scenario->scheme ? SchemeClientResource::make($scenario->scheme) : null,

            // breaks!
            'paywall' => PaywallClientResource::make($scenario),
        ];
    }

    protected function interpretPlacement(Flow $workflow, mixed $id): ?string
    {
        $qualifiers = [
            'findButtonByText' => 'Button',
        ];

        if (Str::startsWith($id, ['triggers.'])) {
            /* @var WorkflowTrigger $trigger */
            $trigger = Arr::get($workflow->triggers, intval($id));

            if (! $trigger) {
                return null;
            }

            return $qualifiers[$trigger->qualifier].' '.$trigger->event;
        }

        // todo: support custom events

        return $id;
    }

}
