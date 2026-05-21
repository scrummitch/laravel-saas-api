<?php

namespace Database\Factories\Usage;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Usage\Metric>
 */
class MetricFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->word.' event';

        return [
            'event_name' => Str::slug($name, '_'),
            'aggregation' => 'LATEST',
            'type' => 'persistent',
        ];
    }
}
