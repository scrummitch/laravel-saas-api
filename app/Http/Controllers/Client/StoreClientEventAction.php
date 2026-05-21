<?php

namespace App\Http\Controllers\Client;

use App\Client\ClientAuthorization;
use App\Convert\ActionEvent;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StoreClientEventAction extends Controller
{
    public function __invoke(Request $request, ClientAuthorization $auth)
    {
        $client = $auth->client;
        $collector = $auth->collector;

        abort_unless(! is_null($client), 404, 'Client not found');

        foreach (Arr::wrap($request->json('events')) as $event) {
            if (Arr::get($event, 'event') === 'activity') {
                $this->queueActivityEvent($event, $auth);

                continue;
            }

            $props = Arr::get($event, 'properties');

            DB::table('client_events')
                ->insertOrIgnore([
                    'client_id' => $client->id,
                    'type' => Arr::get($event, 'type', 'track'),
                    'message_id' => Arr::get($event, 'messageId'),
                    'collector_id' => $collector?->id,
                    'event_name' => Arr::get($event, 'event'),
                    'library' => implode(':', [
                        Arr::get($event, 'library.name'),
                        Arr::get($event, 'library.version'),
                    ]),
                    'properties' => is_string($props) ? $props : json_encode($props),
                    'created_at' => Carbon::parse(Arr::get($event, 'timestamp')),
                ]);
        }

        return response()->noContent();
    }

    public function queueActivityEvent(array $message, ClientAuthorization $auth)
    {
        $event = new ActionEvent(
            data: Arr::get($message, 'properties', []),
            auth: $auth
        );

        if (!isset($event->activity)) {
            return;
        }

        if ($auth->client->environment === 'test') {
            $event->validate();
        }

        $event->activity->apply($event->resolve())->save();
    }
}
