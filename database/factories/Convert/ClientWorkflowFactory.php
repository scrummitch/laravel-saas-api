<?php

namespace Database\Factories\Convert;

use App\Models\Client;
use App\Models\Convert\Flow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Convert\Flow>
 */
class ClientWorkflowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Flow::factory(),
            'client_id' => Client::factory(),
            'is_active' => true,
        ];
    }
}
