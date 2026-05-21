<?php

namespace Tests\Feature\API;

use App\Models\Account\Customer;
use App\Models\Catalog\Feature;
use App\Models\Client;
use App\Models\Usage\Metric;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Tests\TestCase;

class MetricApiControllerTest extends TestCase
{
    protected $seed = true;

    public function test_list_metrics()
    {
        $org = $this->createOrg('test_list_metrics-org');
        $user = User::factory()->create([]);
        $org->users()->save($user, ['role' => 'owner']);

        $features = Feature::factory()
            ->for($org)
            ->count(3)
            ->create([]);

        $metrics = Metric::factory()
            ->for($org)
            ->count(3)
            ->sequence(function (Sequence $sequence) use ($features) {
                return [
                    'feature_id' => $features->get($sequence->index)?->id,
                ];
            })
            ->create();

        $indexRes = $this->getJson('/v1/usage/metrics');
        $indexRes->assertStatus(Response::HTTP_UNAUTHORIZED);

        $this->actingAs($user);
        $indexRes = $this->getJson('/v1/usage/metrics');
        $indexRes->assertStatus(Response::HTTP_OK);
        $this->assertSame(3, $indexRes->json('meta.total'));

        foreach ($indexRes->json('data') as $i => $m) {
            $metric = $metrics->get($i);
            $this->assertSame($metric->event_name, $m['event_name']);
        }
    }

    public function test_create_metric()
    {
        $org = $this->createOrg('test_create_metric-org');
        $user = User::factory()->create([]);
        $org->users()->save($user, ['role' => 'owner']);

        $createRes = $this->postJson('/v1/usage/metrics', [
            'event_name' => 'Test Metric',
        ]);
        $createRes->assertStatus(Response::HTTP_UNAUTHORIZED);

        $this->actingAs($user);

        $createRes = $this->postJson('/v1/usage/metrics', [
            'event_name' => 'Test Metric',
        ]);
        $createRes->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $createRes->assertJsonValidationErrorFor('aggregation');

        $feature = Feature::factory()
            ->for($org)
            ->create();
        $createRes = $this->postJson('/v1/usage/metrics', [
            'event_name' => 'Test Metric',
            'aggregation' => 'LATEST',
            'feature_id' => $feature->getRouteKey(),
        ]);
        $createRes->assertStatus(Response::HTTP_CREATED);
        $this->assertSame('Test Metric', $createRes->json('event_name'));
    }

    public function test_get_metric()
    {
        $org = $this->createOrg('test_create_metric-org');
        $user = User::factory()->create([]);
        $org->users()->save($user, ['role' => 'owner']);

        $getRes = $this->getJson('/v1/usage/metrics');
        $getRes->assertStatus(Response::HTTP_UNAUTHORIZED);

        $feature = Feature::factory()
            ->for($org)
            ->create();
        $metric = Metric::factory()
            ->for($org)
            ->for($feature)
            ->create();

        $getRes = $this->getJson('/v1/usage/metrics/'.$metric->getRouteKey());
        $getRes->assertUnauthorized();

        $this->actingAs($user);
        $getRes = $this->getJson('/v1/usage/metrics/'.Str::random());
        $getRes->assertNotFound();

        $getRes = $this->getJson('/v1/usage/metrics/'.$metric->getRouteKey());
        $getRes->assertOk();
        $this->assertSame($metric->getRouteKey(), $getRes->json('id'));
    }

    public function test_update_metric()
    {
        $org = $this->createOrg('test_update_metric-org');
        $user = User::factory()->create([]);
        $org->users()->save($user, ['role' => 'owner']);

        $feature = Feature::factory()
            ->for($org)
            ->create();
        $metric = Metric::factory()
            ->for($org)
            ->for($feature)
            ->create();

        $updateRes = $this->patchJson('/v1/usage/metrics/'.$metric->getRouteKey());
        $updateRes->assertUnauthorized();

        $this->actingAs($user);

        $updateRes = $this->patchJson('/v1/usage/metrics/'.$metric->getRouteKey(), [
            'event_name' => 'Updated Metric',
        ]);
        $updateRes->assertStatus(Response::HTTP_OK);
        $this->assertSame('Updated Metric', $updateRes->json('event_name'));
    }

    public function test_delete_metric()
    {
        $org = $this->createOrg('test_delete_metric-org');
        $user = User::factory()->create([]);
        $org->users()->save($user, ['role' => 'owner']);

        $feature = Feature::factory()
            ->for($org)
            ->create();
        $metric = Metric::factory()
            ->for($org)
            ->for($feature)
            ->create();

        $deleteRes = $this->deleteJson('/v1/usage/metrics/'.$metric->getRouteKey());
        $deleteRes->assertUnauthorized();

        $this->actingAs($user);

        $deleteRes = $this->deleteJson('/v1/usage/metrics/'.$metric->getRouteKey());
        $deleteRes->assertNoContent();
    }

    public function test_create_usage_event()
    {
        $org = $this->createOrg('test_create_usage_event-org');
        $client = Client::factory()
            ->for($org)
            ->web()
            ->create();

        $customer = Customer::factory()
            ->for($org)
            ->create();

        $eventName = fake()->word.'-event';

        $feature = Feature::factory()
            ->for($org)
            ->create();
        $metric = Metric::factory()
            ->for($org)
            ->for($feature)
            ->create([
                'event_name' => $eventName,
                'field_name' => 'value',
            ]);

        $count = fake()->randomNumber(2);
        $createRes = $this->postJson(
            '/client/usage/events',
            [
                'customer_id' => $customer->reference_id,
                'client_id' => $client->getRouteKey(),
                'event_name' => $eventName,
                'properties' => [
                    'value' => $count,
                ],
            ],
            [
                'Authorization' => 'Bearer '.JWT::encode([
                    'sub' => $client->getRouteKey(),
                ], $client->getSecretStr(), 'HS256', $client->getRouteKey()),
            ]
        );
        $createRes->assertCreated();

        $this->assertDatabaseHas('usage_events', [
            'event_name' => $eventName,
//            'properties' => json_encode(['value' => $count]),
        ]);
    }
}
