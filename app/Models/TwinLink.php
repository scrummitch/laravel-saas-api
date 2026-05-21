<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TwinLink extends Pivot
{
    protected $table = 'twin_links';

    public $timestamps = false;

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function twin(): BelongsTo
    {
        return $this->belongsTo(Twin::class);
    }
}
