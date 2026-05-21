<?php

namespace Database\Factories\Management;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Management\Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'default_currency' => 'USD',
        ];
    }

    public function durable()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Durable',
            ];
        });
    }

    public function flindev()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'flindev',
            ];
        });
    }
}
