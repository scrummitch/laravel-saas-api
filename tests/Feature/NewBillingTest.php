<?php

namespace Tests\Feature;

use App\Billing\ISO4217;
use App\Billing\RenewalCalculator;
use App\Convert\DataObjects\LineItem;
use App\Models\Billing\Charge;
use App\Models\Catalog\Product;
use App\Models\Management\Organization;
use App\Models\Pricing\Scheme;
use Money\Currency;
use Tests\TestCase;

class NewBillingTest extends TestCase
{
    public function test_line_item_variations()
    {
        $variations = $this->generateVariations();

        foreach ($variations as $index => $variation) {
            $org = Organization::factory()->create();
            $scheme = Scheme::factory()->for($org)->create($variation['scheme']);
            $product = Product::factory()->for($org)->create($variation['product']);
            $charge = Charge::factory()
                ->{$variation['charge']['type']}()
                ->for($org)
                ->for($product)
                ->create($variation['charge']);

            $cartData = [
                'properties' => $variation['properties'],
                'quantity' => $variation['quantity'],
                'min_quantity' => $variation['min_quantity'],
                'max_quantity' => $variation['max_quantity'],
            ];

            $calculator = new RenewalCalculator(
                purchasable: $charge,
                customer: null,
                quantity: $variation['quantity'],
            );
            $li = new LineItem($charge, $calculator, $cartData);
            $result = $li->toArray();

            $this->assertLineItemOutput($result, $variation, $index);
        }
    }

    private function generateVariations(): array
    {
        $variations = [];

        for ($i = 0; $i < 10; $i++) {
            $currency = $this->faker->randomElement(['aud', 'usd']);
            $chargeType = $this->faker->randomElement(['standard', 'graduated', 'volume', 'package']);

            $variations[] = [
                'scheme' => [
                    'active_currencies' => ['aud', 'usd'],
                ],
                'product' => [
                    'name' => "Product {$i}",
                    'description' => "Description for Product {$i}",
                ],
                'charge' => [
                    'currency' => new Currency($currency),
                    'amount' => $this->faker->numberBetween(1000, 10000),
                    'type' => $chargeType,
                    'properties' => $this->generateChargeProperties($chargeType),
                    'mode' => $this->faker->randomElement(['in_advance', 'in_arrears']),
                    'name' => "Charge {$i}",
                    'description' => "Description for Charge {$i}",
                ],
                'properties' => [
                    'color' => $this->faker->colorName,
                    'size' => $this->faker->randomElement(['S', 'M', 'L', 'XL']),
                ],
                'quantity' => $this->faker->numberBetween(1, 10),
                'min_quantity' => $this->faker->numberBetween(1, 3),
                'max_quantity' => $this->faker->numberBetween(10, 20),
            ];
        }

        return $variations;
    }

    private function generateChargeProperties(string $type): array
    {
        switch ($type) {
            case 'graduated':
            case 'volume':
                return [
                    ['up_to' => 10, 'unit_amount' => 1000, 'flat_amount' => 5000],
                    ['up_to' => 20, 'unit_amount' => 900, 'flat_amount' => 4500],
                    ['up_to' => null, 'unit_amount' => 800, 'flat_amount' => 4000],
                ];
            case 'package':
                return ['package_size' => 5, 'amount' => 4000, 'free_units' => 0];
            default:
                return [];
        }
    }

    private function assertLineItemOutput(array $result, array $variation, int $index): void
    {
        $this->assertEquals('charge', $result['object'], "Variation {$index}: Incorrect object type");
        $this->assertEquals($variation['charge']['name'], $result['display_name'], "Variation {$index}: Incorrect display name");
        $this->assertEquals($variation['charge']['description'], $result['description'], "Variation {$index}: Incorrect description");
        $this->assertEquals($variation['charge']['currency']->getCode(), $result['currency_code'], "Variation {$index}: Incorrect currency code");

        $this->assertArrayHasKey('quantity', $result, "Variation {$index}: Missing quantity");
        $this->assertEquals($variation['quantity'], $result['quantity']['value'], "Variation {$index}: Incorrect quantity value");
        $this->assertEquals($variation['min_quantity'], $result['quantity']['min'], "Variation {$index}: Incorrect min quantity");
        $this->assertEquals($variation['max_quantity'], $result['quantity']['max'], "Variation {$index}: Incorrect max quantity");

        $this->assertArrayHasKey('properties', $result, "Variation {$index}: Missing properties");
        $this->assertEquals($variation['properties'], $result['properties'], "Variation {$index}: Incorrect properties");

//        $this->assertIsArray($result['charges_summary'], "Variation {$index}: charges_summary should be an array");
//        $this->assertCount(1, $result['charges_summary'], "Variation {$index}: charges_summary should have 1 item");
//        $this->assertEquals($variation['charge']['type'], $result['charges_summary'][0]['type'], "Variation {$index}: Incorrect charge type");

        // Add more specific assertions based on charge type
        switch ($variation['charge']['type']) {
            case 'standard':
//                dd($result,$variation);
                $expectedAmount = $variation['charge']['amount'] * $variation['quantity'];
                $this->assertEquals($expectedAmount, $result['amount_subtotal'], "Variation {$index}: Incorrect amount for standard charge");
                break;
            case 'graduated':
            case 'volume':
                $this->assertGreaterThan(0, intval($result['amount_subtotal']), "Variation {$index}: Amount should be greater than 0 for {$variation['charge']['type']} charge");
                break;
            case 'package':
//                dd($result,$variation);
                // For these types, we'd need to calculate the expected amount based on the properties and quantity
                // This is a simplified check
                $this->assertGreaterThan(0, intval($result['amount_subtotal']), "Variation {$index}: Amount should be greater than 0 for {$variation['charge']['type']} charge");
                break;
        }
    }
}
