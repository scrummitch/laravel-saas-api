<?php

namespace Tests\Feature;

use App\Exceptions\Handler;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Charge;
use App\Models\Catalog\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Convert\Flow;
use App\Models\Intelligence\Scenario;
use App\Models\Management\Organization;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use App\Models\Publish\Rollout;
use App\Models\Stats\Collector;
use App\Models\User;
use App\Models\Values\PlanType;
use GuzzleHttp\Psr7\Query;
use Illuminate\Support\Str;
use Money\Currency;
use Money\Money;
use Tests\Helper\TestsAgainstStripe;
use Tests\TestCase;

class ConvertSessionsTest extends TestCase
{
    use TestsAgainstStripe;

    protected ?Organization $org;
    protected ?User $user;
    protected ?Flow $workflow;
    protected ?Scenario $paywall;

    public function setUp(): void
    {
        parent::setUp();

        $this->org = $this->createOrg(__FUNCTION__);
        $this->user = $this->createUser([], $this->org);
        $this->workflow = Flow::factory()->for($this->org)->create();
        $scheme = $this->setupPricingSchemePlans();
        $this->paywall = Scenario::factory()
            ->for($this->workflow)
            ->for($this->org)
            ->for($scheme)
            ->create();
    }

//    public function test_device_detector()
//    {
//        $chrome = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
//
//        $dd = new DeviceDetector(
//            $chrome,
//        );
//        $dd->skipBotDetection();
//        $dd->parse();
//
//
//        $ip = fake()->ipv4;
//
//        $cityDbReader = new Reader(resource_path('app/GeoLite2-Country.mmdb'));
//
//        $record = null;
//        while ($record === null) {
//            try {
//                $record = $cityDbReader->country($ip);
//            } catch (GeoIp2Exception $e) {
//            }
//        }
//
//        $origin = strval((new Uri(fake()->url))->withPath('')->withQuery(''));
//
//        $p = [
//            'origin' => $origin,
//            'country' => $record?->country?->isoCode,
//            'os'=> Arr::get($dd->getOs(), 'short_name'),
//            'browser' => Arr::get($dd->getClient(), 'short_name'),
//            'timestamp' => now()->timestamp,
//        ];
//        $this->assertNotNull($p['os']);
//        $this->assertNotNull($p['country']);
//    }

    public function test_client_anon_sessions()
    {
        $org = $this->createOrg(__FUNCTION__);
        $client = $org->clients()->where('environment', 'test')->first();

        $params = Query::build([
            'anonymous_id' => $id = 'anon_'.Str::replace('-', '', fake()->uuid),
            'client' => $client->getRouteKey(),
        ]);
        $anonRes = $this->getJson('/client/session?'.$params);
        $anonRes->assertOk();
        $collector = Collector::retrieve($anonRes->json('collector'));
        $this->assertNotNull($collector);

    }

    public function test_client_session_api_identified()
    {
        $org = $this->createOrg(__FUNCTION__);
        $client = $org->clients()->where('environment', 'live')->first();

        $bsp = BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create();
        $client->billing_provider_id = $bsp->id;
        $client->save();

        $workflow = Flow::factory()
            ->for($org)
            ->create([]);

        $handler = new \App\Models\Convert\Handler();
        $handler->flow_id = $workflow->id;
        $handler->event_name = 'custom';
        $handler->save();


        $liveRollout = Rollout::factory()
            ->for($org)
            ->for($client)
            ->for($workflow, 'publishable')
            ->create();

        $pricingScheme = Scheme::factory()
            ->for($org)
            ->create();

        $paywall = Scenario::factory()
            ->for($org)
            ->for($pricingScheme)
            ->for($workflow)
            ->create();

        $this->actingAs($org->owners()->first());
        $wfRes = $this->getJson('v1/convert/workflows');
        $wfRes->assertOk();

        $this->assertNotEmpty($wfRes->json('data'));
        $this->assertSame('workflow', $wfRes->json('data.0.object'));
        $this->assertNotEmpty($wfRes->json('data.0.handlers'));
        $this->assertSame(1, $wfRes->json('data.0.scenarios_count'));
        $this->assertNull($wfRes->json('data.0.opens_count'));

        $res = $this->postJson('client/session', [
            'client_id' => $client->getRouteKey(),
        ], [
            'Authorization' => 'Bearer ' . $client->generateAgentJwt('test'),
            'Origin' => 'https://example.com',
            'REMOTE_ADDR' => '220.240.108.216',
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 7_1_2 like Mac OS X; nl-NL) AppleWebKit/535.22.4 (KHTML, like Gecko) Version/3.0.5 Mobile/8B118 Safari/6535.22.4',
        ]);
        $res->assertOk();

        $collectorId = $res->json('collector');
        $collector = Collector::retrieve($collectorId);
        $this->assertNotNull($collector);
        $this->assertSame('AU', $collector->country);
        $this->assertSame('IOS', $collector->os);
        $this->assertSame('MF', $collector->browser);
        $this->assertSame('https://example.com', $collector->origin);

        $pwId = $res->json('modules.convert.paywalls.0.id');
        $this->assertSame($paywall->getRouteKey(), $pwId);

        // todo: see participation?
        $pwRes = $this->getJson('/client/paywalls/'.$pwId.'?collector='.$collector->getRouteKey(), [
            'Authorization' => 'Bearer ' . $client->generateAgentJwt('test'),
        ]);
        $pwRes->assertOk();

        // should have an open session! should be "entered"
        $session = $collector
            ->activities()
            ->first();
        $this->assertNotNull($session);

        // get sessions for a workflow, show the flags n shit
        $sessRes = $this->getJson('/v1/convert/workflows/'.$workflow->getRouteKey().'/sessions');
        $sessRes->assertOk();

        $this->assertNotEmpty($sessRes->json('data'));
    }

    protected function setupPricingSchemePlans(): Scheme
    {
        $scheme = Scheme::factory()
            ->for($this->org)
            ->create();

        $plan = $this->createPlan($scheme, 'basic', 1000);
        $package = Package::factory()
            ->for($scheme)
            ->create();
        $plan->package_id = $package->id;
        $plan->save();

        return $scheme;
    }

    protected function createPlan(Scheme $scheme, string $name, int $price)
    {
        Plan::unguard();
        $paidProduct = Product::factory()
            ->for($this->org)
            ->premium()
            ->create([
                'name' => $name,
            ]);
        $paidChargePerSeat = Charge::factory()
            ->for($paidProduct)
            ->for($this->org)
            ->create([
                'mode' => 'in_advance',
                'amount' => new Money(fake()->randomNumber(4), new Currency('USD')),
            ]);

        $plan = Plan::factory()
            ->for($this->org)
            ->create([
                'type' => PlanType::standard,
                'currency' => 'USD',
            ]);

        Inclusion::factory()
            ->for($plan)
            ->for($paidProduct)
            ->for($paidChargePerSeat, 'charge')
            ->create();

        return $plan;
    }

    public function test_paywall_events_story_identified()
    {
        $scheme = $this->setupPricingSchemePlans();
        $testClient = $this->org
            ->clients()
            ->where('environment', 'test')
            ->first();

        $plan = $scheme->plans->first();
//        $this->paywall->checkout_config = [
//            'line_items' => [
//                [
//                    'id' => $plan->getRouteKey(),
//                    'object' => 'plan', // add plan ulid??
//                    'quantity' => 1,
//                ]
//            ],
//        ];
        $this->paywall->save();

        $jwt = $testClient->generateAgentJwt('some-random-identifier');

        $sessionRes = $this->postJson('client/session', [], [
            'Authorization' => 'Bearer '.$jwt,
            'User-Agent' => fake()->userAgent,
        ]);

        $collector = Collector::retrieve($sessionRes->json('collector'));

        $paywall = Scenario::retrieve($sessionRes->json('modules.convert.paywalls.0.id'));
        $this->assertNotNull($paywall);

        $query = Query::build([
            'collector' => $collector->getRouteKey(),
            'paywall' => $paywall->getRouteKey(),
            'placement' => 'event:custom-event',
        ]);
    }

}
