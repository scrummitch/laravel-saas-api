<?php

namespace App\Models\Intelligence;

use App\Billing\IntervalCast;
use App\Database\Model;
use App\Database\Traits\HasLookupKey;
use App\Models\Convert\BundleItem;
use App\Models\Convert\Element;
use App\Models\Convert\Flow;
use App\Models\Management\Organization;
use App\Models\Pricing\Scheme;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int      $id
 * @property int      $flow_id
 * @property string   $lookup_key
 * @property int|null $scheme_id
 * @property int|null $element_id
 * @property
 * @property array    $conditions
 * @property string   $current_state
 *
 * @property Flow $flow
 * @property Scheme|null $scheme
 * @property Collection<BundleItem> $bundleItems
 * @property string $intent
 */
class Scenario extends Model
{
    use HasLookupKey,
        HasFactory;

    protected $table = 'intel_scenarios';

    protected $fillable = [
        'organization_id',
        'flow_id',
        'scheme_id',
        'element_id',
        'lookup_key',
        'name',
        'intent',
        'conditions',
        'properties',
        'bundle_rules',
        'renew_interval',
        'current_state',
    ];

    protected $casts = [
        'properties' => 'json',
        'conditions' => 'array',
        'bundle_rules' => 'array',
        'renew_interval' => IntervalCast::class,
    ];

    protected $with = [
        'bundleItems',
        'bundleItems.purchasable',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function bundleItems(): HasMany
    {
        return $this->hasMany(BundleItem::class);
    }

    public function purchasables()
    {
        return $this->morphToMany(
            null, // No specific model since it's a morph
            'purchasable',
            BundleItem::class,
            'scenario_id',
            'purchasable_id'
        );
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function element(): BelongsTo
    {
        return $this->belongsTo(Element::class);
    }
}
