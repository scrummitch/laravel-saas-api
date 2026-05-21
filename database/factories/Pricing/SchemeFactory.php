<?php

namespace Database\Factories\Pricing;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pricing\Scheme>
 */
class SchemeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lookup_key' => $this->faker->slug,
            'version_name' => 'v1',
            'version_number' => 1,
            'name' => 'Scheme v1',
        ];
    }
}
