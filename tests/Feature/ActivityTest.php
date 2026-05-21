<?php

namespace Tests\Feature;

use App\Intel\Enums\FlowState;
use App\Models\Convert\Element;
use App\Models\Convert\Flow;
use App\Models\Convert\Handler;
use App\Models\Intelligence\ActionType;
use App\Models\Intelligence\Activity;
use App\Models\Intelligence\ActivityAction;
use App\Models\Stats\Collector;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_flow_activity_lifecycle(): void
    {
        $this->markTestSkipped();
        $user = $this->createUser();
        $org = $user->currentOrganization;

        $flow = Flow::factory()
            ->for($org)
            ->create([
                'scenarios' => [
                    [
                        'conditions' => [
                        ],
                        'action' => 'setupPaymentMethod',
                        'context' => [
                        ],
                    ],
                ],
            ]);

        $gate = Element::factory()
            ->for($org)
            ->gate()
            ->create([
                'conditions' => [
                    'combinator' => 'and',
                    'not' => false,
                    'rules' => [
                        [
                            'criteria' => 'hasPaymentMethod',
                            'op' => 'eq',
                            'value' => false,
                        ],
                    ],
                ],
            ]);

        $handler = new Handler();
        $handler->flow_id = $flow->id;
        $handler->element_id = $gate->id;
        $handler->event_name = 'unlock';
        $handler->save();

        $client = $org->clients()->first();
        $collector = Collector::factory()->for($client)->create();

        $res = $this->postJson('/client/events', [
            'client' => $client->getRouteKey(),
            'collector' => $collector->getRouteKey(),
            'events' => [
                [
                    'type' => 'activity',
                    'event' => 'started',
                    'properties' => [
                        'activity_id' => $activityId = Str::uuid()->toString(),
                        'handler_id' => $handler->uuid,
                        'scenario' => 'paywall-A',
                    ]
                ]
            ]
        ]);
        $res->assertSuccessful();

        $res = $this->postJson('/client/events', [
            'client' => $client->getRouteKey(),
            'collector' => $collector->getRouteKey(),
            'events' => [
                [
                    'type' => 'activity',
                    'event' => 'interaction',
                    'properties' => [
                        'activity_id' => $activityId,
                        'target' => 'stage',
                        'delta' => [0, 1],
                    ]
                ]
            ]
        ]);

        $res = $this->postJson('/client/events', [
            'client' => $client->getRouteKey(),
            'collector' => $collector->getRouteKey(),
            'events' => [
                [
                    'type' => 'activity',
                    'event' => 'finished',
                    'properties' => [
                        'activity_id' => $activityId,
                        'state' => 'complete',
                    ],
                ]
            ]
        ]);
        $res->assertSuccessful();

        $activity = Activity::retrieve($activityId);

        $this->assertSame('completed', $activity->current_state);
        $this->assertNotNull($activity->finished_at);
    }

    public function test_action_events_flow(): void
    {
        $user = $this->createUser();
        $org = $user->currentOrganization;

        // Create client and collector
        $client = $org->clients()->first();
        $collector = Collector::factory()->for($client)->create();

        $activityId = Str::uuid()->toString();
        $messageId = Str::uuid()->toString();

        // Simulate entry action event
        $response = $this->postJson('/client/events', [
            'client' => $client->getRouteKey(),
            'collector' => $collector->getRouteKey(),
            'events' => [
                [
                    'type' => 'track',
                    'event' => 'activity',
                    'properties' => [
                        'id' => $messageId,
                        'type' => ActionType::Entry->name,
                        'activity' => $activityId,
                        'flow' => 'test-flow',
                        'scenario' => 'test-scenario',
                        'entry' => [
                            'variant' => 'A'
                        ],
                        'metadata' => [
                            'source' => 'test'
                        ]
                    ]
                ]
            ]
        ]);
//        dd($response->exception);

        $response->assertSuccessful();

        // Verify activity was created with correct state
        /* @var Activity $activity*/
        $activity = Activity::query()->where('uuid', $activityId)->first();

        $flow = $activity->scenario->flow;
        $this->assertNotNull($flow);
        $this->assertSame('test-flow', $flow->lookup_key);
        $this->assertSame('test-flow', $flow->getRouteKey());
        $this->assertSame(FlowState::Staged, $flow->current_state);

        $scenario = $activity->scenario;
        $this->assertNotNull($scenario);

        // todo: creator is the client?

        $this->assertNotNull($activity);
        $this->assertNotNull($activity->started_at);
        $this->assertNotNull($activity->last_interaction_at);

        // Verify action event was created
        $action = ActivityAction::where('activity_id', $activity->id)->first();
        $this->assertNotNull($action);
        $this->assertEquals(ActionType::Entry, $action->type);
        $this->assertEquals(['source' => 'test'], $action->metadata);

        // Simulate complete action event
        $response = $this->postJson('/client/events', [
            'client' => $client->getRouteKey(),
            'collector' => $collector->getRouteKey(),
            'events' => [
                [
                    'type' => 'track',
                    'event' => 'activity',
                    'properties' => [
                        'type' => ActionType::Complete->name,
                        'activity' => $activityId,
                        'flow' => 'test-flow',
                        'scenario' => 'test-scenario',
                        'entry' => [
                            'variant' => 'A'
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertSuccessful();

        // Verify activity state was updated
        $activity->refresh();
        $this->assertEquals('completed', $activity->current_state);
        $this->assertNotNull($activity->finished_at);

        // Verify both actions exist
        $this->assertEquals(2, ActivityAction::where('activity_id', $activity->id)->count());
    }
}
