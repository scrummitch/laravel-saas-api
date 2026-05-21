<?php

namespace Tests\Feature;

use App\Models\Catalog\Feature;
use App\Models\Catalog\FeatureSet;
use App\Models\Catalog\Product;
use App\Models\Usage\Metric;
use App\Models\User;
use Illuminate\Support\Arr;
use Tests\TestCase;

class FeaturesTest extends TestCase
{
    public function test_get_features()
    {
        $org = $this->createOrg('test_get_features');
        $user = User::factory()->create();
        $user->organizations()->attach($org->id);

        $res = $this->getJson('/v1/catalog/features');
        $res->assertStatus(401);

        $this->actingAs($user);
        $res = $this->getJson('/v1/catalog/features');
        $res->assertStatus(200);
        $this->assertCount(0, $res->json('data'));

        /* @var Feature $feature */
        $feature = Feature::factory()
            ->for($org)
            ->create();
        $product = Product::factory()
            ->for($org)
            ->create();
        $feature->products()->attach($product->id);
        $metric = Metric::factory()
            ->for($org)
            ->for($feature)
            ->create();
        $featureSet = FeatureSet::factory()
            ->for($org)
            ->create();
        $feature->featureSet()->associate($featureSet);
        $feature->save();

        $this->assertNotNull($feature->featureSet);

        $this->assertCount(1, $product->features);
        $this->assertCount(1, $product->productFeatures);

        $res = $this->getJson('/v1/catalog/features');
        $res->assertStatus(200);

        $resource = $res->json('data.0');

        $this->assertCount(1, $res->json('data'));

        $this->assertSame($feature->lookup_key, $resource['id']);
        $this->assertSame($feature->name, $resource['name']);
        $this->assertSame($feature->description, $resource['description']);

        $this->assertSame(null, $resource['released_at']);

        $this->assertSame(
            $feature->created_at->timestamp,
            $resource['created_at']
        );
        $this->assertSame($feature->updated_at->timestamp, $resource['updated_at']);

        $this->assertCount(1, Arr::get($resource, 'metrics.data'));
        $this->assertNotNull($resource['feature_set']);
        $fs = FeatureSet::retrieve(Arr::get($resource, 'feature_set.id'));
        $this->assertNotNull($fs);

        $mt = Metric::retrieve(Arr::get($resource, 'metrics.data.0.id'));
        $this->assertNotNull($mt);

        $mtRes = $this->getJson('v1/usage/metrics/'.$mt->getRouteKey());
        $mtRes->assertStatus(200);

        $fsRes = $this->getJson('v1/catalog/feature_sets/'.$fs->getRouteKey());
        $fsRes->assertStatus(200);
    }
}
