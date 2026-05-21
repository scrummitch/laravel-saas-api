<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Web Test Client',
            'environment' => 'test',
            'secret' => Str::random(32),
        ];
    }

    public function web(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'web',
            ];
        });
    }
}
