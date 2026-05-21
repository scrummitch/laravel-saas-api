<?php

namespace Database\Factories\Media;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssetFactory extends Factory
{
    public function definition()
    {
        return [

        ];
    }

    public function icon()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'icon.jpg',
                'current_state' => 'ready',
                'original_name' => 'icon.jpg',
                'content_type' => 'image/jpeg',
                'size' => 1024 * 1024,
                'key' => Str::random().'-icon.jpg',
                'bucket' => 'media-assets',
                'visibility' => 'public-read',

            ];
        });
    }
}
