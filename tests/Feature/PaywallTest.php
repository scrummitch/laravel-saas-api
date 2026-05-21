<?php

namespace Tests\Feature;

use App\Models\Billing\BillingProvider;
use App\Models\Convert\Element;
use App\Models\Convert\Flow;
use App\Models\Intelligence\Scenario;
use App\Models\Pricing\Scheme;
use App\Models\Stats\Collector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PaywallTest extends TestCase
{
    public function test_paywall_has_correct_structure()
    {
        $org = $this->createOrg(__FUNCTION__);

        $client = $org->clients()->first();
        $bsp = BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create();
        $client->billingProvider()->associate($bsp);
        $client->save();

        $workflow = Flow::factory()
            ->for($org)
            ->create();

        $scheme = Scheme::factory()
            ->for($org)
            ->create();

        $element = Element::factory()
            ->for($org)
            ->paywall()
            ->create([
                'view' => json_decode(File::get(resource_path('paywall-templates/sws-paywall-stages.json')), true),
            ]);

        $paywall = Scenario::factory()
            ->for($workflow)
            ->for($org)
            ->for($scheme)
            ->for($element)
            ->create([
            ]);

        $collector = Collector::factory()
            ->for($client)
            ->create();
        $res = $this->get('client/paywalls/'.$paywall->getRouteKey().'?collector='.$collector->getRouteKey(), [
            'Authorization' => 'Bearer '.$client->generateAgentJwt('test'),
        ]);
        $res->assertOk();

        $this->assertSame($paywall->getRouteKey(), $res->json('paywall.id'));
        $this->assertSame('paywall', $res->json('paywall.object'));
//        $this->assertSame('setup', $res->json('paywall.mode'));
//        $this->assertNull($res->json('paywall.settings'));
        $this->assertSame('upgrade', $res->json('paywall.intent'));
        $this->assertArrayHasKey('split-checkout@v1', $res->json('paywall.layouts'));
        $this->assertEmpty($res->json('paywall.conditions'));
        $this->assertEmpty($res->json('paywall.checkout_config'));

        $stage0 = $res->json('paywall.stages.0');
        $this->assertNotNull($stage0);
        $this->assertSame(['info'], Arr::get($stage0, 'provides'));
        $this->assertSame(['sm' => 'split-checkout@v1'], Arr::get($stage0, 'layout'));
        $this->assertNotNull(Arr::get($stage0, 'view.promo'));
    }

    public function test_workflow_product_summary()
    {
        $org = $this->createOrg(__FUNCTION__);

        $this->fillOrgWithProducts($org);

        $workflow = Flow::factory()
            ->for($org)
            ->create();

        $plan = $org->plans->first();
        $package = $plan->package;


        $paywall = Scenario::factory()
            ->for($workflow)
            ->for($org->schemes->first())
            ->create([
            ]);
        $purchasables = [
            [
                'purchasable_type' => 'plan',
                'purchasable_id' => $plan->id,
                'scenario_id' => $paywall->id,
            ],
            [
                'purchasable_type' => 'package',
                'purchasable_id' => $package->id,
                'scenario_id' => $paywall->id,
            ],
        ];
        DB::table('convert_bundle_items')
            ->insert($purchasables);

        $this->actingAs($org->users()->first());
        $res = $this->getJson('/v1/intel/flows/'.$workflow->getRouteKey());
        $res->assertOk();

        $this->assertCount(2, $res->json('purchasables'));
    }
}
