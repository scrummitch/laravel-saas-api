<?php

namespace Database\Factories\Convert;

use App\Convert\Enums\ElementType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Convert\Element>
 */
class ElementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'display_name' => 'Element Test',
            'lookup_key' => 'element-test',
//            'current_state' => 'draft',

        ];
    }

    public function paywall()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => ElementType::Paywall,
                'mode' => 1,
//                'insertion_type' => 'manual',
            ];
        });
    }
}
