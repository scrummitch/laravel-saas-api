<?php

namespace Database\Factories\Billing;

use App\Billing\Charges\GraduatedCharge;
use App\Billing\Charges\PackageCharge;
use App\Billing\Charges\StandardCharge;
use App\Billing\Charges\VolumeCharge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Money\Currency;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Billing\Charge>
 */
class ChargeFactory extends Factory
{
    protected static $modelName = 'App\Models\Billing\Charge';

    public function modelName()
    {
        return self::$modelName;
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word.' Charge',
            'currency' => new Currency('usd'),
            'amount' => $this->faker->randomNumber(4),
            'mode' => 'in_advance',
            'type' => 'standard',
        ];
    }

    public function standard()
    {
        self::$modelName = StandardCharge::class;
        return $this->state(function (array $attributes) {
            return [
                'currency' => new Currency($this->faker->randomElement(['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD'])),
                'amount' => 1000,
                'mode' => 'in_advance',
                'type' => 'standard',
                'name' => 'Standard Charge',
            ];
        });
    }

    public function graduated()
    {
        self::$modelName = GraduatedCharge::class;
        return $this->state(function (array $attributes) {
            return [
                'currency' => new Currency($this->faker->randomElement(['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD'])),
                'amount' => null,
                'mode' => 'in_advance',
                'type' => 'graduated',
                'name' => 'Graduated Charge',
                'properties' => [
                    ['up_to' => 10, 'unit_amount' => 1000, 'flat_amount' => 5000],
                    ['up_to' => 20, 'unit_amount' => 900, 'flat_amount' => 4500],
                    ['up_to' => null, 'unit_amount' => 800, 'flat_amount' => 4000],
                ],
            ];
        });
    }

    public function volume()
    {
        self::$modelName = VolumeCharge::class;
        return $this->state(function (array $attributes) {
            return [
                'currency' => new Currency($this->faker->randomElement(['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD'])),
                'amount' => null,
                'mode' => 'in_advance',
                'type' => 'volume',
                'name' => 'Volume Charge',
                'properties' => [
                    ['up_to' => 100, 'unit_amount' => 1000],
                    ['up_to' => 1000, 'unit_amount' => 900],
                    ['up_to' => null, 'unit_amount' => 800],
                ],
            ];
        });
    }

    public function package()
    {
        self::$modelName = PackageCharge::class;
        return $this->state(function (array $attributes) {
            return [
                'currency' => new Currency($this->faker->randomElement(['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD'])),
                'amount' => null,
                'mode' => 'in_advance',
                'type' => 'package',
                'name' => 'Package Charge',
                'properties' => [
                    // $5 per 100 units with first 100 free
                    'package_size' => 100,
                    'free_units' => 100,
                    'amount' => 500,
                ],
            ];
        });
    }

    public function inArrears()
    {
        return $this->state(function (array $attributes) {
            return [
                'mode' => 'in_arrears',
            ];
        });
    }
}
