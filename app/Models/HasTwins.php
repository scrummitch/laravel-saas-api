<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasTwins
{
    public function twins(): MorphMany
    {
        return $this->morphMany(Twin::class, 'linkable');
    }
}
