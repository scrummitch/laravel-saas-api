<?php

namespace Database\Factories\Catalog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFamilyFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => 'default',
            'lookup_key' => Str::random(6).'-'.Str::slug($this->faker->words(2, true)),
        ];
    }
}
