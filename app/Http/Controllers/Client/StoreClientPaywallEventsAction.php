<?php

namespace App\Http\Controllers\Client;

use App\Client\ClientAuthorization;
use App\Convert\PaywallService;
use App\Http\Controllers\Controller;
use App\Models\Intelligence\ActionType;
use App\Models\Intelligence\ActivityAction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * NOTE: this is all old now and should move to activity_actions
 * @deprecated
 */
class StoreClientPaywallEventsAction extends Controller
{
    public function __invoke(Request $request, ClientAuthorization $auth)
    {
        $evt = $request->all();

        $scenario = PaywallService::resolveScenario(
            $auth->client->organization,
            $request->input('paywall')
        );

        $paywalls = new PaywallService(
            scenario: $scenario,
            auth: $auth,
            request: $request
        );

        $activity = $paywalls->getActivity();

        $action =  new ActivityAction([
            'activity_id' => $activity->id,
            'event_id' => Str::uuid()->toString(),
            'event_name' => null,
            'type' => ActionType::make($request->input('event_name')),
            'properties' => Arr::get($evt, 'data'),
            'metadata' => Arr::get($evt, 'metadata'),
            'created_at' => $this->getEventTimestamp($evt),
        ]);

        $action->save();

        $activity->apply($action);

        return response('', 204);
    }

    private function getEventTimestamp(array $evt): Carbon
    {
        return ! empty(Arr::get($evt, 'ts'))
            ? Carbon::create(Arr::get($evt, 'ts'))
            : now();
    }
}
