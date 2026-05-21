<?php

namespace Database\Factories\Pricing;

use App\Models\Management\Organization;
use App\Models\Values\PlanType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pricing\Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::random(8).' - '.$this->faker->word.' Plan '.$this->faker->randomNumber(4);

        return [
            'name' => $name,
            'lookup_key' => Str::slug($name),
            'renew_interval' => $this->faker->randomElement(['P1M', 'P1Y']),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD']),
            'organization_id' => Organization::factory(),
            'type' => PlanType::standard,
        ];
    }

    public function standard()
    {
        return $this->state([
            'type' => PlanType::standard,
        ]);
    }
}
