<?php

namespace Tests\Feature;

use App\Billing\RenewalCalculator;
use App\Convert\DataObjects\LineItem;
use App\Http\Resources\Client\CheckoutClientResource;
use App\Models\Account\Agent;
use App\Models\Account\Customer;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Schedule;
use App\Models\Billing\Subscription;
use App\Models\Catalog\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductFamily;
use App\Models\Client;
use App\Models\Convert\Checkout;
use App\Models\Convert\CheckoutState;
use App\Models\Management\Organization;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use App\Models\Store\Purchase;
use App\Models\User;
use App\Models\Values\PlanType;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * @group example
 */
class ClientTest extends TestCase
{
    public function test_client_origins()
    {
        $org = $this->createOrg('test_client_origins');

        $origin = new Uri($this->faker->url);
        $origin = $origin->getScheme().'://'.$origin->getHost();

        /* @var Client $client */
        $client = $org
            ->clients()
            ->first();

        if ($origin && ! collect($client->allowed_origins)->contains($origin)) {
            $client->pending_origins = collect($client->pending_origins)->filter()->push($origin)->unique();
            $client->save();
        }

        $this->assertSame($origin, $client->pending_origins->first());
        $this->assertSame('('.$origin.')', $client->originList());
    }

    public function test_api_store_client()
    {
        $org = Organization::factory()
            ->create();
        $owner = User::factory()
            ->create();

        $org->users()->attach($owner, ['role' => 'owner']);

        $forbiddenResponse = $this->postJson(route('api/clients.store'), [
            'name' => 'Test Client',
        ]);
        $forbiddenResponse->assertStatus(401);

        $this->actingAs($owner);

        $validationResponse = $this->postJson(route('api/clients.store'), [
            'name' => 'Test Client',
            'type' => 'poop',
        ]);

        $createResponse = $this->postJson(route('api/clients.store'), [
            'name' => 'Test Client',
            'type' => 'web',
            'platform' => 'plandalf-js',
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonStructure([
            'id',
            'name',
            'type',
            'platform',
            'created_at',
            'updated_at',
        ]);
        $this->assertStringStartsWith('client_', $createResponse->json('id'));
        $client = Client::retrieve($createResponse->json('id'));

        $getClientResponse = $this->getJson(route('api/clients.show', $createResponse->json('id')));
        $getClientResponse->assertOk();
        //        $getClientResponse->assertJson($createResponse->json());

        $getSecretResponse = $this->getJson(route('api/clients.secret', $createResponse->json('id')));
        $getSecretResponse->assertOk();
        $getSecretResponse->assertJsonStructure([
            'secret',
        ]);
        $secret = $getSecretResponse->json('secret');
        $this->assertNotNull($secret);
        $this->assertSame($client->secret, $secret);

        $updateClientRequest = $this->putJson(route('api/clients.update', $createResponse->json('id')), [
            'name' => 'Test Client '.Str::ulid(),
        ]);
        $updateClientRequest->assertOk();
        $updateClientRequest->assertJsonStructure([
            'id',
            'name',
            'type',
            'platform',
            'created_at',
            'updated_at',
        ]);

        $deleteClientRequest = $this->deleteJson(route('api/clients.destroy', $createResponse->json('id')));
        $deleteClientRequest->assertNoContent();

        $client->refresh();
        $this->assertNotNull($client->deleted_at);
    }

    /**
     * A basic feature test example.
     */
    public function test_create_durable_client(): void
    {
        $org = Organization::factory()
            ->create();

        $client = Client::factory()
            ->for($org)
            ->create();

        $this->assertNotNull($client);
        $this->assertNotNull($client->ulid);
        $this->assertNotNull($client->secret);
        $this->assertNotNull($client->getSecretStr());
        $this->assertNotNull($client->getRouteKey());
        $this->assertTrue(Str::startsWith($client->getRouteKey(), 'client_'));

        $userId = Str::uuid()->toString();
        $groupId = Str::random(6).'-'.Str::slug(fake()->company);

        $encodedJwt = JWT::encode([
            'sub' => $userId,
            'groups' => [$groupId],
        ], $client->getSecretStr(), 'HS256', $client->getRouteKey());

        $decodedJwt = (array) JWT::decode($encodedJwt, new Key($client->getSecretStr(), 'HS256'));

    }

    public function test_specific_scheme_for_client()
    {
        $org = $this->createOrg();

        $client = $org->clients()->first();
        $scheme = Scheme::factory()
            ->for($org)
            ->create();
        $jwt = $client->generateAgentJwt('test');

        $getSessionRes = $this->getJson('/client/session', [
            'Authorization' => 'Bearer '.$jwt,
        ]);
        $getSessionRes->assertOk();
        $this->assertSame($scheme->getRouteKey(), $getSessionRes->json('config.catalog.scheme.id'));
    }

    public function test_checkout_resource_creates_correct_summary()
    {
        $org = $this->createOrg();
        $client = $org->clients()->first();
        $scheme = Scheme::factory()->for($org)->create();
        $agent = Agent::factory()
            ->for($org)
            ->create();
        $bsp = BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create();

        $checkout = Purchase::factory()
            ->for($org)
            ->for($bsp, 'billing_provider')
            ->create([
                'provider_name' => 'plandalf',
                'current_state' => CheckoutState::STARTED,
                'intent' => 'upgrade',
                'currency' => 'USD',
            ]);

        $resource = new CheckoutClientResource($checkout);

        $plan = Plan::factory()
            ->create([
            ]);

        $calculator = new RenewalCalculator(
            purchasable: $plan,
            customer: $checkout->customer,
        );

        $items = [
            new LineItem($plan, $calculator, [
                'quantity' => 1,
                'properties' => [],
            ]),
        ];

        $summary = $resource->generateSummaryFromLineItems(collect($items));
        $this->assertSame($plan->name, $summary);

        $addon = Plan::factory()
            ->create([
                'type' => PlanType::addon,
            ]);

        $addonCalculator = new RenewalCalculator(
            purchasable: $addon,
            customer: $checkout->customer,
        );

        $items = [
            new LineItem($plan, $calculator, [
                'quantity' => 1,
                'properties' => [],
            ]),
            new LineItem($addon, $addonCalculator, [
                'quantity' => 1,
                'properties' => [],
            ]),
        ];
        $summary = $resource->generateSummaryFromLineItems(collect($items));

        $this->assertSame($plan->name.' with '.$addon->name.' add on', $summary);
    }

    public function test_subscriptions_are_loaded()
    {
        $org = $this->createOrg();
        $client = $org->clients()->first();
        $scheme = Scheme::factory()->for($org)->create();
        $customer = Customer::factory()
            ->for($org)
            ->create();
        $family = ProductFamily::factory()
            ->for($org)
            ->create();
        $product = Product::factory()
            ->for($org)
            ->for($family)
            ->create();
        $schedule = Schedule::factory()
            ->for($org)
            ->for($customer)
            ->create();
        $agent = Agent::factory()
            ->for($org)
            ->create();
        $agent->associateWithCustomer($customer);

        $plan = Plan::factory()
            ->create();
        Inclusion::factory()
            ->for($plan)
            ->for($product)
            ->create([

            ]);

        Subscription::factory()
            ->active()
            ->for($customer)
            ->for($org)
            ->for($plan)
            ->create([
                'quantity' => 1,
            ]);

        // attach plan to scheme (hasManyThrough)
        /* @var Package $package */
        Package::unguard();
        $package = $scheme->packages()->create([]);
        Package::reguard();
        $plan->package()->associate($package)->save();
        $plan->refresh();

        $this->assertNotNull($plan->package);

        $getSessionRes = $this->getJson('/client/session', [
            'Authorization' => 'Bearer '.$client->generateAgentJwt('test', ['customer' => $customer->getRouteKey()]),
        ]);
//        dd($getSessionRes->exception, $getSessionRes->content());
        $this->assertNotNull($getSessionRes->json('customer'));
        $this->assertNotNull($getSessionRes->json('schedule'));
        $this->assertNotEmpty($getSessionRes->json('schedule.subscriptions'));
        $this->assertNotEmpty($getSessionRes->json('schedule.subscriptions.0.plan.products'));
        $this->assertNotEmpty($getSessionRes->json('schedule.subscriptions.0.plan.products.0.family'));
        $this->assertNotEmpty($getSessionRes->json('schedule.subscriptions.0.plan.products.0.id'));
        $this->assertNotEmpty($getSessionRes->json('subscriptions.0.plan.product_key'));
        $this->assertNotEmpty($getSessionRes->json('subscriptions.0.plan.product_family'));
    }
}
