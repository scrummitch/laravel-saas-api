<?php

namespace App\Http\Controllers\Client;

use App\Client\ClientAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Account\Customer;
use App\Models\Usage\UsageEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CreateUsageEventController extends Controller
{
    public function __invoke(Request $request, ClientAuthorization $auth)
    {
        $client = $auth->client;

        abort_unless($client, Response::HTTP_UNAUTHORIZED);

        $request->validate([
            'customer_id' => [
                'required',
                Rule::exists(Customer::class, 'reference_id'),
            ],
            'event_name' => [
                'required',
                'string',
            ],
            'properties' => [
                'required',
                'array',
            ],
        ]);

        $customer = Customer::query()
            ->where('reference_id', $request->input('customer_id'))
            ->where('organization_id', $client->organization_id)
            ->firstOrFail();

        $usageEvent = new UsageEvent;
        $usageEvent->organization_id = $client->organization_id;
        $usageEvent->client_id = $client->id;
        $usageEvent->customer_id = $customer->id;
        $usageEvent->agent_id = null;
        $usageEvent->event_name = $request->input('event_name');
        $usageEvent->unique_id = $request->input('unique_id', Str::uuid()->getHex()->toString());
        $usageEvent->properties = $request->input('properties', []);
        $usageEvent->save();

        $usageEvent->generateSummary();

        return response('', Response::HTTP_CREATED);
    }
}
