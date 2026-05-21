<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ClientSessionRequest;
use App\Http\Resources\Client\CustomerClientResource;
use App\Http\Resources\Client\ElementClientResource;
use App\Http\Resources\Client\FlowClientResource;
use App\Http\Resources\Client\PlanClientResource;
use App\Http\Resources\Client\ScheduleClientResource;
use App\Http\Resources\Client\SchemeClientResource;
use App\Http\Resources\Client\SubscriptionClientResource;
use App\Models\Catalog\Inclusion;
use App\Models\Client;
use App\Models\Convert\Element;
use App\Models\Convert\Flow;
use App\Models\Intelligence\Scenario;
use App\Models\Pricing\Scheme;
use App\Models\Publish\Participation;
use App\Models\Publish\Rollout;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;
use Sentry\State\Scope;

class GetClientSessionAction extends Controller
{
    public function __invoke(ClientSessionRequest $request)
    {
        $collector = $request->collector();
        $auth = $request->auth();

        $client = $auth->client;
        $customer = $auth->customer;

        defer(function () use ($customer) {
            if ($customer && $customer->wasRecentlyCreated) {
                $customer->syncStripe(force: true);
            } elseif ($customer) {
                $customer->syncStripe();
            }
        });

        \Sentry\configureScope(function (Scope $scope) use ($customer, $client)  {
            $scope->setContext('client_session', [
                'customer' => $customer?->getRouteKey(),
                'client' => $client->getRouteKey(),
            ]);
        });

        $client->loadMissing('organization', 'schemes');
        $customer?->loadMissing([
            'schedule',
            'subscriptions' => fn($query) => $query->active(),
            'subscriptions.plan.package',
            'subscriptions.plan.inclusions',
            'subscriptions.plan.inclusions.product',
            'subscriptions.plan.inclusions.product.productFamily',
        ]);
        $customer?->schedule?->setRelation('subscriptions', $customer?->subscriptions);

        $this->updateClientOrigins($client, $request);

        // 5ms
        $scheme = $this->retrieveScheme($client);
        $config = [
            'catalog' => [
                'scheme' => $scheme ? new SchemeClientResource($scheme) : null,
            ],
            'billing_providers' => $client->billingProvider ? [
                [
                    'service' => $client->billingProvider->service,
                    'environment' => $client->billingProvider->environment,
                    'key' => $client->billingProvider->getPublicApiKey(),
                ],
            ] : [],
        ];

//        $rollouts = $this->getRollouts($request);

        $flows = Flow::query()
            ->where('organization_id', $client->organization_id)
            ->with([
                'scenarios',
                'scenarios.element',
            ])
            ->get();

        $res = [
            'collector' => $collector->getRouteKey(),
            'customer' => $customer ? new CustomerClientResource($customer) : null,

            'schedule' => $customer?->schedule ? new ScheduleClientResource($customer->schedule) : null,
            'subscriptions' => SubscriptionClientResource::collection($customer->subscriptions ?? []),

            'config' => $config,
            'modules' => [
                'entitlements' => $customer?->entitlements->map(function (Inclusion $inclusion) {
                    return $inclusion->toArray();
                }),
                'convert' => [
                    // load elements with "handlers"
//                    'elements' => ElementClientResource::collection($elements),
                    'workflows' => $flows
                        ->map(function (Flow $flow) {
                            return [
                                'id' => $flow->getRouteKey(),
                                'triggers' => $flow->triggers ?? [],
                                'paywalls' => $flow->scenarios->map(fn (Scenario $paywall) => $paywall->getRouteKey()),
                            ];
                        })
                        ->values(),
                    // TODO: remove this later?
                    'paywalls' => $flows
                        ->pluck('scenarios')
                        ->filter()
                        ->flatten()
                        ->map(function (Scenario $paywall) {
                            // todo: replace this with scenarios?
                            return [
                                'id' => $paywall->getRouteKey(),
                                'conditions' => $paywall->conditions,

                                // its action may be an "element" here.

                                'element' => [
                                    'object' => '',
                                    'url' => '/elements/'.$paywall->getRouteKey().'.json',
                                ]
                            ];
                        })
                        ->values(),
                ],
            ],
        ];

        return response()->json($res);
    }

    private function retrieveScheme(Client $client): ?Scheme
    {
        $scheme = $client
            ->schemes()
            ->latest()
            ->first();

        return $scheme;
    }

    private function updateClientOrigins(Client $client, ClientSessionRequest $request)
    {
        $origin = $request->header('Origin');

        if ($origin && $client->type === 'web' && ! collect($client->pending_origins)->contains($origin)) {
            $client->pending_origins = collect($client->pending_origins)->filter()->push($origin)->unique();
            $client->save();
        }
    }

    /**
     * @return Collection<Rollout>
     */
    private function getRollouts(Request $request): Collection
    {
        $client = $request->client();
        $agent = $request->auth()->agent;
        $customer = $request->auth()->customer;

        $rollouts = $client
            ->rollouts()
            ->where('publishable_type', 'workflow')
            ->with(['publishable', 'publishable.paywalls'])
            ->get()
            ->filter(function (Rollout $rollout) use ($agent, $customer) {
                if (! $rollout->is_active) {
                    return false;
                }

                $participation = Participation::query()
                    ->where(function (\Illuminate\Database\Eloquent\Builder $query) use ($customer, $agent) {
                        if (! is_null($customer)) {
                            $query->where(function ($q) use ($customer) {
                                $q->where('scope_type', 'customer')->where('scope_id', $customer);
                            });
                        }

                        if ($agent) {
                            $query->orWhere(function ($q) use ($agent) {
                                $q->where('scope_type', 'agent')->where('scope_id', $agent->id);
                            });
                        }
                    })
                    ->where('rollout_id', $rollout->id)
                    ->first();

                if (! is_null($participation)) {
                    return boolval($participation->value);
                }

                $pass = false;

                foreach ($rollout->rules ?? [] as $rule) {
                    $pass = match (Arr::get($rule, 'condition')) {
                        'isSandboxUser' => $agent->is_sandbox_user,
                        null => true,
                        // if there a lottery for the rule, we need to run it
                    };

                    if ($pass) {
                        break;
                    }

                    $pass = Lottery::odds(1, 100)->choose()[0];
                }

                if (empty($rollout->rules)) {
                    $pass = true;
                }

                if (! $agent) {
                    // no agent?
                    return;
                }

                $v = [
                    'scope_id' => $agent?->id,
                    'scope_type' => 'agent',
                    'rollout_id' => $rollout->id,
                    'value' => $pass,
                ];
                DB::table('publish_participations')
                    ->insertOrIgnore($v);

                //                event(new RolloutMatchEvent($rollout, $agent));

                return $pass;
            });

        return $rollouts;
    }
}
