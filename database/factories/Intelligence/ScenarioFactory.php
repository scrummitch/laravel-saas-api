<?php

namespace Database\Factories\Intelligence;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ScenarioFactory extends Factory
{

    public function definition()
    {
        return [
            'display_name' => 'hello',
            'intent' => 'upgrade',
            'lookup_key' => Str::random(32),
            'properties' => [
                'hello' => 'world',
            ],
            'bundle_rules' => [
                'hello' => 'world',
            ],
            'renew_interval' => 'P1Y',
        ];
    }
}
