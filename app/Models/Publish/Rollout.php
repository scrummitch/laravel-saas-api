<?php

namespace App\Models\Publish;

use App\Database\Model;
use App\Database\Traits\HasNiceUlids;
use App\Models\Client;
use App\Models\Management\Organization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @module Publish
 *
 * @property int $id
 * @property int $organization_id
 * @property int $client_id
 * @property string $publishable_type
 * @property int $publishable_id
 * @property array $rules
 * @property bool $is_active
 * @property Organization $organization
 * @property Client $client
 * @property Model $publishable
 * @property int $percentage
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Rollout extends Model
{
    use HasFactory,
        HasNiceUlids;

    /**
     * @var array[]|mixed
     */
    protected $table = 'publish_rollouts';

    protected $casts = [
        'rules' => 'array',
        'is_active' => 'bool',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function publishable(): MorphTo
    {
        return $this->morphTo();
    }

    public function participations(): HasMany
    {
        return $this->hasMany(Participation::class);
    }

    public function percentage(): int
    {
        $rule = collect($this->rules)->firstWhere('type', 'percentage');

        return $rule ? $rule['value'] : 0;
    }
}
