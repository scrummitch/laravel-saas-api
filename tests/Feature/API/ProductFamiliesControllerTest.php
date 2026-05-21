<?php

namespace Tests\Feature\API;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductFamily;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductFamiliesControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUser([]);
        $this->org = $this->user->currentOrganization;
    }

    public function test_can_create_product_family()
    {
        $this->actingAs($this->user);

        $product = Product::factory()
            ->for($this->org)
            ->create();

        $createResponse = $this->postJson(route('api/catalog.product_families.store'), [
            'name' => $this->faker->words(2, true),
            'lookup_key' => Str::random(6).'-'.Str::slug($this->faker->company),
            'selected_products' => [
                $product->getRouteKey(),
            ],
        ]);

        $createResponse->assertStatus(201);
        $family = ProductFamily::retrieve($createResponse->json('id'));
        $this->assertNotNull($family);

        $product->refresh();

        $this->assertSame($product->product_family_id, $family->id);
    }

    /**
     * @dataProvider createProductFamilyValidationDataProvider
     */
    public function test_create_product_family_validations(array $payload, string $expectedErrorMessage, bool $createProductWithFamily)
    {
        $this->actingAs($this->user);

        if ($createProductWithFamily) {
            $product = Product::factory()
                ->for($this->org)
                ->for(ProductFamily::factory()->for($this->org))
                ->create();

            $payload = array_merge($payload, ['selected_products' => $product->getRouteKey()]);
        }

        $createResponse = $this->postJson(route('api/catalog.product_families.store'), array_merge([
            'name' => $this->faker->words(2, true),
            'lookup_key' => Str::random(6).'-'.Str::slug($this->faker->company),
        ], $payload));

        $createResponse->assertStatus(422);
        $this->assertSame($createResponse->json('message'), $expectedErrorMessage);
    }

    public static function createProductFamilyValidationDataProvider()
    {
        return [
            [
                [
                    'selected_products' => ['invalid-product'],
                ],
                'The selected_products includes invalid product id',
                false,
            ],
            [
                [
                    'selected_products' => [],
                ],
                "Invalid selected_products: You can't update a product that's already connected to other family.",
                true,
            ],
        ];
    }

    public function test_can_update_product_family()
    {
        $this->actingAs($this->user);

        $family = ProductFamily::factory()
            ->for($this->org)
            ->create();

        $product1 = Product::factory()
            ->for($this->org)
            ->for($family)
            ->create();

        $product2 = Product::factory()
            ->for($this->org)
            ->create();

        $response = $this->putJson(route('api/catalog.product_families.update', $family->getRouteKey()), [
            'name' => $this->faker->words(2, true),
            'lookup_key' => Str::random(6).'-'.Str::slug($this->faker->company),
            'selected_products' => [
                $product1->getRouteKey(),
            ],
        ]);

        $response->assertStatus(200);
        $product1->refresh();
        $product2->refresh();

        $this->assertSame($product1->product_family_id, $family->id);
        $this->assertNull($product2->product_family_id); //was uncheck
    }

    /**
     * @dataProvider updateProductFamilyValidationDataProvider
     */
    public function test_update_product_family_validations(array $payload, string $expectedErrorMessage, bool $createProductWithFamily)
    {
        $this->actingAs($this->user);
        $family = ProductFamily::factory()
            ->for($this->org)
            ->create();

        if ($createProductWithFamily) {
            $product = Product::factory()
                ->for($this->org)
                ->for(ProductFamily::factory()->for($this->org))
                ->create();

            $payload = array_merge($payload, ['selected_products' => $product->getRouteKey()]);
        }

        $createResponse = $this->putJson(route('api/catalog.product_families.update', $family->getRouteKey()), array_merge([
            'name' => $this->faker->words(2, true),
            'lookup_key' => Str::random(6).'-'.Str::slug($this->faker->company),
        ], $payload));

        $createResponse->assertStatus(422);
        $this->assertSame($createResponse->json('message'), $expectedErrorMessage);
    }

    public static function updateProductFamilyValidationDataProvider()
    {
        return [
            [
                [
                    'selected_products' => ['invalid-product'],
                ],
                'The selected_products includes invalid product id',
                false,
            ],
            [
                [
                    'selected_products' => [],
                ],
                "Invalid selected_products: You can't update a product that's already connected to other family.",
                true,
            ],
        ];
    }
}
