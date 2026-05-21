<?php

namespace App\Models\Pricing;

use App\Database\Model;
use App\Database\Traits\HasVersionedLookupKey;
use App\Models\Catalog\Product;
use App\Models\Management\Organization;
use App\Models\Values\PlanType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $ancestor_id
 * @property int $successor_id
 * @property string $lookup_key
 * @property string $name
 * @property string $version_name
 * @property int $version_number
 * @property Collection<Plan> $plans
 * @property Collection<Plan> $offers
 * @property Collection<Plan> $addons
 * @property Organization $organization
 * @property Carbon $generated_at
 * @property array $active_currencies
 * @property array $active_intervals
 */
class Scheme extends Model
{
    use HasFactory,
        HasVersionedLookupKey;

    protected $casts = [
        'generated_at' => 'datetime',
        'active_currencies' => 'array',
        'active_intervals' => 'array',
    ];

    protected $table = 'pricing_schemes';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plans(): HasManyThrough
    {
        return $this->hasManyThrough(
            Plan::class,
            Package::class,
            'pricing_scheme_id',
            'package_id',
            'id',
            'id'
        );
    }

    public function packages(): HasMany
    {
        return $this
            ->hasMany(Package::class, 'pricing_scheme_id')
            ->chaperone();
    }
}
