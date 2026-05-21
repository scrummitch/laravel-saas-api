<?php

namespace Tests\Feature;

use App\Billing\Coupon;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Charge;
use App\Models\Catalog\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Client;
use App\Models\Convert\Checkout;
use App\Models\Convert\Flow;
use App\Models\Intelligence\Scenario;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use App\Models\Publish\Rollout;
use App\Models\Stats\Collector;
use App\Models\Store\Purchase;
use Firebase\JWT\JWT;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CouponCustomerTest extends TestCase
{
    public function test_sandbox_agent_gets_applied_coupon()
    {
        $org = $this->createOrgWithPaywall('test_sandbox_agent_gets_applied_coupon');
        /* @var Client $client */
        $client = $org->liveClient();
        $client->refresh();

        // TODO: fix coupon here!

        $bsp = BillingProvider::factory()
            ->stripe()
            ->for($org)
            ->create([
                'environment' => 'live',
            ]);
        $client->billing_provider_id = $bsp->id;
        $client->save();

        $jwt = JWT::encode([
            'sub' => $userId = 'user_'.Str::uuid()->toString(),
            'aud' => 'sandbox',
        ], $client->getSecretStr(), 'HS256', $client->getRouteKey());

        $sessionRes = $this->getJson('/client/session', [
            'Authorization' => 'Bearer '.$jwt,
        ]);
        $sessionRes->assertOk();
        $this->assertNotEmpty($sessionRes->json('modules.convert.paywalls'));
        $collector = Collector::query()
            ->where('uuid', $sessionRes->json())
            ->first();
        $this->assertNotNull($collector);
        $agent = $collector->agent;
        $this->assertNotNull($agent);
        $this->assertTrue($agent->is_sandbox_user);

        DB::table('twins')
            ->insert([
                'reference_id' => Coupon::PLANDALF_SANDBOX_CODE,
                'organization_id' => $org->id,
                'type' => \Stripe\Coupon::class,
                'connector_id' => $org->liveBillingProvider->id,
                'connector_type' => 'billing_provider',
                'data' => json_encode([
                    'id' => Coupon::PLANDALF_SANDBOX_CODE,
                    'amount_off' => null,
                    'currency' => 'usd',
                    'duration' => 'once',
                    'livemode' => true,
                    'max_redemptions' => 1,
                    'name' => 'plandalf sandbox',
                    'percent_off' => 100,
                    'redeem_by' => null,
                    'times_redeemed' => 0,
                    'valid' => true,
                ]),
            ]);

        $paywall = $org
            ->workflows()
            ->first()
            ->scenarios()
            ->first();
        $paywallRes = $this->getJson('/client/paywalls/'.$paywall->getRouteKey().'?collector='.$sessionRes->json('collector'), [
            'Authorization' => 'Bearer '.$jwt,
        ]);
        $paywallRes->assertOk();

        $authZ = [
            'Authorization' => 'Bearer '.$jwt,
            'Plandalf-Cart' => $paywallRes->json('cart_token'),
        ];
        $checkout = Purchase::retrieve($paywallRes->json('checkout.id'));


        $startRes = $this->postJson('/client/events', [
            'collector' => $sessionRes->json('collector'),
            'events' => [
                [
                    'event' => 'activity',
                    'properties' => [
                        'type' => 'start',
                        'activity' => $checkout->activity->getRouteKey(),
                        'flow' => $paywall->flow->getRouteKey(),
                    ],
                ],
            ],
        ], $authZ);
        $startRes->assertNoContent();

        $data = $paywallRes->json('checkout');

        $this->assertSame(0, $data['amount_total']);
        $this->assertNotNull(Arr::get($data, 'discounts'));
        $this->assertCount(1, $paywallRes->json('checkout.line_items'));
        // amount subtotal = ?
        $li = Arr::get($data, 'line_items.0');

        /* @var Plan $plan */
        $plan = $org->plans()->first();

        // TODO :fix charges
        $expectedTotal = $plan->charges()->first()->amount;

        // see total is 0, but subtotal is the full amount
        // also see the discounted amount is the subtotal (100% off)
        $this->assertNotSame(0, $li['amount_total']);
        $this->assertSame($expectedTotal->getAmount(), $li['amount_subtotal']);
        $this->assertSame($expectedTotal->getAmount(), $li['amount_discount']);
    }

    private function createOrgWithPaywall(string $string)
    {
        $user = $this->createUser([]);
        $org = $user->currentOrganization;

        $client = $org->clients()->first() ?: Client::factory()->for($org)->create();
        $billing = BillingProvider::factory()->for($org)->stripe()->create();

        $scheme = Scheme::factory()
            ->for($org)
            ->create();
        $product = Product::factory()
            ->for($org)
            ->create();
        // todo: twin needs to be created?!
        $baseCharge = Charge::factory()
            ->for($org)
            ->for($product)
            ->standard()
            ->create([
                'currency' => $org->default_currency,
            ]);
        $package = Package::factory()
            ->for($scheme)
            ->create();
        $plan = Plan::factory()
            ->for($org)
            ->for($package)
            ->create([
                'currency' => $org->default_currency,
            ]);
        Inclusion::factory()
            ->for($plan)
            ->for($product)
            ->for($baseCharge, 'charge')
            ->create();

        $workflow = Flow::factory()
            ->for($org)
            ->create();
        // create a rollout
        Rollout::factory()
            ->for($workflow, 'publishable')
            ->for($client)
            ->for($org)
            ->create([
                'is_active' => true,
            ]);
        /* @var Scenario $paywall */
        $paywall = Scenario::factory()
            ->for($org)
            ->for($workflow)
            ->for($scheme)
            ->create([
            ]);
        $bi = $paywall->bundleItems()->create([
            'purchasable_id' => $plan->id,
            'purchasable_type' => 'plan',
        ]);

        return $org;
    }
}
