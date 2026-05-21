<?php

namespace Database\Factories\Convert;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Convert\Flow>
 */
class FlowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::uuid()->toString().' workflow',
            'triggers' => [
                [
                    'listen' => 'custom',
                    'event' => 'TestEvent',
                ]
            ],
        ];
    }
}
