<?php

namespace Database\Factories\Billing;

use App\Models\Pricing\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
        ];
    }

    public function active(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'current_state' => 'active ',
            ];
        });
    }
}
