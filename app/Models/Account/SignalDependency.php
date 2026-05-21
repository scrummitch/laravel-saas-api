<?php

namespace App\Models\Account;

use App\Database\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property Signal $signal
 * @property Model $linkable
 * @property int $relation_type
 * @property int|null $quantity
 * @property float|null $delta
 */
class SignalDependency extends Model
{
    public $table = 'intel_dependencies';

    public $timestamps = false;

    protected $fillable = [
        'signal_id',
        'linkable_type',
        'linkable_id',
        'relation_type',
        'quantity',
        'delta',
    ];

    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
