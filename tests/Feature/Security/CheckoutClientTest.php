<?php

namespace Tests\Feature\Security;

use App\Models\Account\Customer;
use App\Models\Billing\BillingProvider;
use App\Models\Client;
use App\Models\Convert\CheckoutState;
use App\Models\Management\Organization;
use App\Models\Store\Purchase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Client-JWT checkout boundary.
 *
 *   - PATCH /client/checkouts/{purchase} only accepts non-terminal state
 *     transitions (started, abandoned). Pre-fix it accepted any field on
 *     the Purchase via foreach-and-assign — including `current_state =
 *     completed` which the server is supposed to set after Stripe confirms.
 *
 *   - Purchase route-model binding is no longer tenant-blind on the
 *     client-JWT routes. A cross-tenant purchase id returns 404, not 200.
 */
class CheckoutClientTest extends TestCase
{
    use DatabaseTransactions;

    private function freshClient(?Organization $org = null): Client
    {
        $org ??= $this->createOrg();

        $bsp = $org->billingProviders()->first()
            ?? BillingProvider::factory()->stripe()->for($org)->create();

        $client = $org->clients()->first()
            ?? Client::factory()->for($org)->create();

        if (! $client->billing_provider_id) {
            $client->billing_provider_id = $bsp->id;
            $client->save();
        }

        return $client;
    }

    private function newPurchase(Organization $org, array $attrs = []): Purchase
    {
        $bsp = $org->billingProviders()->first()
            ?? BillingProvider::factory()->stripe()->for($org)->create();
        $customer = Customer::factory()->for($org)->for($bsp, 'billingProvider')->create();

        return Purchase::factory()
            ->for($org)
            ->for($bsp, 'billing_provider')
            ->for($customer)
            ->create(array_merge(['currency' => 'USD'], $attrs));
    }

    private function jwt(Client $client): array
    {
        return ['Authorization' => 'Bearer '.$client->generateAgentJwt('user_' . uniqid('', true))];
    }

    public function test_patching_current_state_to_started_succeeds(): void
    {
        $org = $this->createOrg();
        $client = $this->freshClient($org);
        $purchase = $this->newPurchase($org, ['current_state' => CheckoutState::CREATED]);

        $this->patchJson('/client/checkouts/'.$purchase->getRouteKey(), [
            'current_state' => 'started',
        ], $this->jwt($client))->assertStatus(200);

        $this->assertSame(CheckoutState::STARTED, $purchase->fresh()->current_state);
    }

    public function test_patching_current_state_to_completed_is_rejected(): void
    {
        $org = $this->createOrg();
        $client = $this->freshClient($org);
        $purchase = $this->newPurchase($org, ['current_state' => CheckoutState::CREATED]);

        $this->patchJson('/client/checkouts/'.$purchase->getRouteKey(), [
            'current_state' => 'completed',
        ], $this->jwt($client))->assertStatus(422);

        $this->assertSame(CheckoutState::CREATED, $purchase->fresh()->current_state);
    }

    public function test_patching_current_state_to_failed_is_rejected(): void
    {
        $org = $this->createOrg();
        $client = $this->freshClient($org);
        $purchase = $this->newPurchase($org, ['current_state' => CheckoutState::CREATED]);

        $this->patchJson('/client/checkouts/'.$purchase->getRouteKey(), [
            'current_state' => 'failed',
        ], $this->jwt($client))->assertStatus(422);
    }

    public function test_unknown_fields_in_patch_body_are_silently_dropped(): void
    {
        $org = $this->createOrg();
        $client = $this->freshClient($org);
        $purchase = $this->newPurchase($org, ['current_state' => CheckoutState::CREATED]);

        $this->patchJson('/client/checkouts/'.$purchase->getRouteKey(), [
            'current_state' => 'started',
            'organization_id' => 999999,
            'amount_total' => 0,
            'customer_id' => 999999,
        ], $this->jwt($client))->assertStatus(200);

        $fresh = $purchase->fresh();
        $this->assertSame($org->id, $fresh->organization_id, 'organization_id must not be overwritten');
        $this->assertSame(CheckoutState::STARTED, $fresh->current_state);
    }

    public function test_cross_tenant_purchase_lookup_returns_404(): void
    {
        $orgA = $this->createOrg();
        $orgB = $this->createOrg();

        $clientA = $this->freshClient($orgA);
        $purchaseB = $this->newPurchase($orgB);

        $this->getJson('/client/checkouts/'.$purchaseB->getRouteKey(), $this->jwt($clientA))
            ->assertStatus(404);
    }

    public function test_cross_tenant_purchase_mutation_returns_404(): void
    {
        $orgA = $this->createOrg();
        $orgB = $this->createOrg();

        $clientA = $this->freshClient($orgA);
        $purchaseB = $this->newPurchase($orgB, ['current_state' => CheckoutState::CREATED]);

        $this->patchJson('/client/checkouts/'.$purchaseB->getRouteKey(), [
            'current_state' => 'started',
        ], $this->jwt($clientA))->assertStatus(404);

        $this->assertSame(
            CheckoutState::CREATED,
            $purchaseB->fresh()->current_state,
            'cross-tenant patch must not mutate the foreign purchase',
        );
    }
}
