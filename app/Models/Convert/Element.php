<?php

namespace App\Models\Convert;

use App\Convert\Enums\ElementType;
use App\Database\Model;
use App\Database\Traits\HasLookupKey;
use App\Models\Management\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int    $organization_id
 * @property string $lookup_key
 *
 * @property string $display_name
 *
 * @property ElementType $type
 *
 * @property object $frame
 *
 * @property array $view
 * @property array $conditios
 */
class Element extends Model
{
    use HasFactory,
        HasLookupKey;

    protected $table = 'convert_elements';

    protected $casts = [
        'type' => ElementType::class,
        'view' => 'json',
        'conditions' => 'json',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function handlers(): HasMany
    {
        return $this->hasMany(Handler::class);
    }
}
