<?php

namespace Database\Factories\Catalog;

use App\Models\Values\ProductStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Catalog\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $n = $this->faker->randomNumber(7);
        return [
            'name' => $name = 'prod_'.$n,
            'lookup_key' => Str::slug($name. ' '.$n, '-'),
            'version_number' => $v = $this->faker->numberBetween(1, 100),
            'version_name' => 'v'.$v,
        ];
    }

    public function free()
    {
        return $this->state([
            'lookup_key' => 'free_product',
            'status' => ProductStatus::active,
            'display_name' => 'Free',
        ]);
    }

    public function premium()
    {
        return $this->state([
            'lookup_key' => 'premium_product',
            'status' => ProductStatus::active,
            'display_name' => 'Premium',
        ]);
    }
}
