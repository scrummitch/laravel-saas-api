<?php

namespace Tests\Feature\Security;

use App\Models\Catalog\Feature;
use App\Models\Catalog\Product;
use App\Models\Client;
use App\Models\Usage\Metric;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cross-tenant guarantees.
 *
 * Each of these would have been exploitable before recent fixes:
 *   - missing or stub policies allowed reading/writing other orgs' resources
 *   - PATCH /v1/users/me with `last_organization_id` switched the caller into
 *     a foreign organization (Organization::retrieve bypasses tenant scoping
 *     for the Organization class itself)
 *   - StoreInclusionRequest / UpdateInclusionRequest had no org scope on the
 *     `product` / `metric` exists rule, so an attacker could attach another
 *     org's catalog entries to their own plan
 */
class TenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private function twoOrgsAndUsers(): array
    {
        $orgA = $this->createOrg();
        $orgB = $this->createOrg();

        $userA = $this->createUser([], $orgA);
        $userB = $this->createUser([], $orgB);

        // The createUser helper attaches the user to the org; mirror what the
        // CurrentOrganization computed property reads from. Direct property
        // assignment intentionally — `last_organization_id` is not fillable.
        $userA->last_organization_id = $orgA->id;
        $userA->save();
        $userB->last_organization_id = $orgB->id;
        $userB->save();

        return [$orgA, $orgB, $userA, $userB];
    }

    /**
     * `Model::resolveRouteBindingQuery` scopes route bindings by the user's
     * current organization, so cross-tenant lookups appear as 404 rather than
     * 403 — that's better security than a "yes it exists but you can't see it"
     * leak. Either status is acceptable as long as it isn't 200.
     */
    public function test_user_cannot_view_clients_belonging_to_another_org(): void
    {
        [, $orgB, $userA] = $this->twoOrgsAndUsers();

        $clientFromB = Client::factory()->for($orgB)->create();

        Sanctum::actingAs($userA);

        $response = $this->getJson('/v1/clients/'.$clientFromB->getRouteKey());

        $this->assertContains($response->status(), [403, 404], 'cross-tenant read should not succeed');
    }

    public function test_user_cannot_delete_clients_belonging_to_another_org(): void
    {
        [, $orgB, $userA] = $this->twoOrgsAndUsers();

        $clientFromB = Client::factory()->for($orgB)->create();

        Sanctum::actingAs($userA);

        $response = $this->deleteJson('/v1/clients/'.$clientFromB->getRouteKey());

        $this->assertContains($response->status(), [403, 404], 'cross-tenant delete should not succeed');
        $this->assertNotNull($clientFromB->fresh(), 'client should still exist');
    }

    public function test_user_listing_clients_only_sees_own_org(): void
    {
        [$orgA, $orgB, $userA] = $this->twoOrgsAndUsers();

        $mine = Client::factory()->for($orgA)->create(['name' => 'mine']);
        Client::factory()->for($orgB)->create(['name' => 'not mine']);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/v1/clients')->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('mine'));
        $this->assertFalse($names->contains('not mine'));
    }

    /**
     * The showpiece: a one-request cross-tenant takeover that wasn't in the
     * original reviewer feedback. Closed by verifying membership before
     * assigning the new last_organization_id.
     */
    public function test_patching_last_organization_id_to_a_foreign_org_returns_422(): void
    {
        [, $orgB, $userA] = $this->twoOrgsAndUsers();

        Sanctum::actingAs($userA);

        $this->patchJson('/v1/users/me', [
            'last_organization_id' => $orgB->id,
        ])->assertStatus(422);

        $this->assertSame($userA->fresh()->last_organization_id, $userA->last_organization_id);
    }

    public function test_patching_last_organization_id_to_a_member_org_succeeds(): void
    {
        $userA = $this->createUser();
        $secondOrg = $this->createOrg();
        $secondOrg->users()->attach($userA, ['role' => 'member']);

        Sanctum::actingAs($userA);

        $this->patchJson('/v1/users/me', [
            'last_organization_id' => $secondOrg->id,
        ])->assertStatus(200);

        $this->assertSame($secondOrg->id, $userA->fresh()->last_organization_id);
    }

    public function test_inclusion_store_rejects_product_from_another_org(): void
    {
        [$orgA, $orgB, $userA] = $this->twoOrgsAndUsers();

        $this->fillOrgWithProducts($orgA);
        $plan = $orgA->plans()->first();

        $foreignProduct = Product::factory()->for($orgB)->create();

        Sanctum::actingAs($userA);

        $this->postJson('/v1/pricing/plans/'.$plan->getRouteKey().'/inclusions', [
            'product' => $foreignProduct->getRouteKey(),
        ])->assertStatus(422);
    }

    public function test_inclusion_update_rejects_metric_from_another_org(): void
    {
        [$orgA, $orgB, $userA] = $this->twoOrgsAndUsers();

        $this->fillOrgWithProducts($orgA);
        $plan = $orgA->plans()->first();
        $inclusion = $plan->inclusions()->first();

        $foreignFeature = Feature::factory()->for($orgB)->create();
        $foreignMetric = Metric::factory()->for($orgB)->for($foreignFeature)->create();

        Sanctum::actingAs($userA);

        $this->patchJson('/v1/pricing/plans/'.$plan->getRouteKey().'/inclusions/'.$inclusion->getRouteKey(), [
            'metric' => $foreignMetric->id,
        ])->assertStatus(422);
    }
}
