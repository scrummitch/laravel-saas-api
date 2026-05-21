<?php

namespace App\Models\Convert;

use App\Database\Model;
use App\Models\Intelligence\Scenario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class BundleItem extends Pivot
{
    protected $table = 'convert_bundle_items';

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function purchasable(): MorphTo
    {
        return $this->morphTo();
    }
}
