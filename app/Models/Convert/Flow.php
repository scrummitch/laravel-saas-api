<?php

namespace App\Models\Convert;

use App\Convert\DataObjects\DTOArray;
use App\Convert\DataObjects\WorkflowTrigger;
use App\Database\Model;
use App\Database\Traits\HasLookupKey;
use App\Intel\Enums\FlowState;
use App\Models\Client;
use App\Models\HasAttachments;
use App\Models\Intelligence\Activity;
use App\Models\Intelligence\Scenario;
use App\Models\Management\Organization;
use App\Models\Publish\Rollout;
use App\Observers\WorkflowObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @module Convert
 *
 * Paywall container
 *
 * @property int       $id
 * @property int       $organization_id
 * @property string    $lookup_key
 * @property string    $name
 * @property array     $triggers
 * @property FlowState $current_state
 *
 * @property Organization         $organization
 * @property Collection<Client>   $clients
 * @property Collection<Scenario> $scenarios
 */
#[ObservedBy(WorkflowObserver::class)]
class Flow extends Model
{
    use HasAttachments,
        HasFactory,
        SoftDeletes,
        HasLookupKey;

    protected $table = 'convert_workflows';

    protected $casts = [
        'triggers' => DTOArray::class.':'.WorkflowTrigger::class,
        'current_state' => FlowState::class,
    ];

    protected $fillable = [
        'name',
        'triggers',
        'status',
        'lookup_key',
    ];

    protected $with = [
        'handlers',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function rollouts(): MorphMany
    {
        return $this->morphMany(Rollout::class, 'publishable');
    }

    public function rolloutsWithEnrollmentCount()
    {
        return $this
            ->rollouts()
            ->withCount('participations');
    }

    public function activities(): HasManyThrough
    {
        return $this->hasManyThrough(
            Activity::class,
            Scenario::class,
            'flow_id',         // Foreign key on intel_scenarios table (changed from xflow_id)
            'scenario_id',     // Foreign key on intel_activities table (changed from xscenario_id)
            'id',              // Local key on flows table (changed from xid)
            'id'
        );
    }

    public function handlers(): HasMany
    {
        return $this
            ->hasMany(Handler::class)
            ->chaperone();
    }

    public function scenarios(): HasMany
    {
        return $this
            ->hasMany(Scenario::class)
            ->chaperone();
    }

    public function images(): MorphToMany
    {
        return $this->multiAttachment('image');
    }

    public function clients(): BelongsToMany
    {
        return $this
            ->belongsToMany(Client::class, Rollout::class, 'publishable_id')
            ->wherePivot('publishable_type', 'workflow')
            ->withPivot(['is_active']);
    }

    public function getAllPurchasables(): \Illuminate\Support\Collection
    {
        return $this->scenarios
            ->map(fn (Scenario $scenario) => $scenario->bundleItems)
            ->flatten()
            ->unique(function ($item) {
                return get_class($item).$item->getRouteKey();
            });
    }
}
