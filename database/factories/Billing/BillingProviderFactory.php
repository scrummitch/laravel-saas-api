<?php

namespace Database\Factories\Billing;

use App\Models\Billing\BillingProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Billing\BillingProvider>
 */
class BillingProviderFactory extends Factory
{
    protected $model = BillingProvider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'environment' => $this->faker->boolean ? 'live' : 'test',
        ];
    }

    public function stripe(): Factory
    {
        return $this->state([
            'type' => 'stripe',
            'lookup_key' => 'acct_'.Str::ulid()->toBase58(),
            'secret' => Str::random(64),
        ]);
    }
}
