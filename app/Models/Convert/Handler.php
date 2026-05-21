<?php

namespace App\Models\Convert;

use App\Database\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Element|null $element
 * @property Flow         $flow
 *
 * @property int      $id
 * @property int|null $element_id
 * @property int      $flow_id
 * @property string   $listen     Event Listener type
 * @property string   $qualifier  Event Listener Qualifier function
 * @property string   $event_name Event name
 * @property array    $props      Event arguments
 */
class Handler extends Model
{
    use HasFactory,
        HasUuids;

    protected $table = 'convert_handlers';

    protected $casts = [
        'props' => 'json'
    ];

    public $timestamps = false;

    public function uniqueIds()
    {
        return [$this->getRouteKeyName()];
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function element(): BelongsTo
    {
        return $this->belongsTo(Element::class);
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }
}
