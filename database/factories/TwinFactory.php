<?php

namespace Database\Factories;

use App\Models\Values\TwinType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Twin>
 */
class TwinFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
        ];
    }

    public function stripeProduct(): Factory
    {
        return $this->state(function ($attributes) {
            $createdAt = Carbon::make($this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s.u'));
            $id = 'prod_'.Str::ulid()->toBase58();

            return [
                'type' => TwinType::Product,
                'reference_id' => $id,
                'data' => [
                    'id' => $id,
                    'object' => 'product',
                    'active' => true,
                    'created' => $createdAt->timestamp,
                    'livemode' => false,
                    'name' => $this->faker->words(3, true).' product',
                    'statement_descriptor' => null,
                    'metadata' => [],
                ],
                'reference_created_at' => $createdAt,
            ];
        });
    }
}
