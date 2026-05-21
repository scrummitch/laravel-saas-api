<?php

namespace App\Models\Intelligence;

use App\Database\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property int $activity_id
 *
 * @property ActionType  $type
 * @property string|null $event_name
 * @property array $properties
 * @property array $metadata
 * @property Carbon $created_at
 *
 * @property Activity $activity
 */
class ActivityAction extends Model
{
    const UPDATED_AT = null;

    protected $table = 'intel_actions';

    protected $guarded = [];

    protected $casts = [
        'type' => ActionType::class,
        'properties' => 'json',
        'metadata' => 'json',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
