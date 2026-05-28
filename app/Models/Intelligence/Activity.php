<?php

namespace App\Models\Intelligence;

use App\Database\Model;
use App\Models\Account\Agent;
use App\Models\Account\Customer;
use App\Models\Account\Signal;
use App\Models\Client;
use App\Models\Convert\Attribution;
use App\Models\Convert\Element;
use App\Models\Convert\Flow;
use App\Models\Convert\Handler;
use App\Models\Stats\Collector;
use App\Models\Store\Purchase;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Arr;

/**
 *
 * @property int     $id
 * @property string  $uuid
 *
 * @property int         $parent_id
 * @property int         $scenario_id
 * @property int         $client_id
 * @property int         $collector_id
 * @property int         $customer_id
 *
 * @property bool        $has_entered
 * @property bool        $has_started
 * @property bool        $has_completed
 *
 * @property string      $result_code
 * @property string      $result_message
 * @property string      $error_reason
 *
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 *
 * @property Carbon|null $last_interaction_at
 * @property string      $current_state
 *
 * @property Flow          $flow
 * @property Activity|null $parent
 * @property Collector     $collector
 * @property Customer|null $customer
 * @property Client        $client
 * @property Scenario      $scenario
 */
class Activity extends Model
{
    use HasUuids;

    protected $table = 'intel_activities';

    protected $fillable = [
        'uuid',
        'parent_id',
        'scenario_id',
        'client_id',
        'collector_id',
        'customer_id',
        'agent_id',
        'handler_id',
        'has_entered',
        'has_started',
        'has_completed',
        'result_code',
        'result_message',
        'error_reason',
        'started_at',
        'finished_at',
        'last_interaction_at',
        'current_state',
    ];

    protected $casts = [
        'has_entered' => 'boolean',
        'has_started' => 'boolean',
        'has_completed' => 'boolean',
    ];

    public function getDates()
    {
        return [
            'last_interaction_at',
            'created_at',
            'updated_at',
            'started_at',
            'finished_at',
        ];
    }

    public function uniqueIds()
    {
        return [$this->getRouteKeyName()];
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function views(): HasMany
    {
        return $this
            ->hasMany(ActivityAction::class, 'activity_id')
            ->where('type', ActionType::Entry);
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function flow()
    {
        return $this->hasOneThrough(Flow::class, Scenario::class, 'flow_id', 'id', 'scenario_id', 'flow_id');
    }

    public function handler()
    {
        return $this->belongsTo(Handler::class);
    }

    public function purchase()
    {
        return $this->hasOne(Purchase::class);
    }

    public function attribution()
    {
        return $this->hasOne(Attribution::class);
    }

    public function signal(): HasOne
    {
        return $this->hasOne(Signal::class, 'convert_activity_id');
    }

    // this is the "trigger" element from the handler
    public function element(): HasOneThrough
    {
        return $this
            ->through(Handler::class)
            ->has(Element::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ActivityAction::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }

    public function apply(ActivityAction $action): Activity
    {
        if (!$this->has_entered) {
            $this->has_entered = true;
        }

        // start is specific to
        if ($action->type->canStartActivities() && !$this->has_started) {
            $this->has_started = true;
            $this->current_state = 'started';
            $this->started_at = now();
        }

        if ($action->type->canCompleteActivities() && !$this->has_completed) {
            $this->has_completed = true;
            $this->current_state = 'completed';
            $this->finished_at = now();
        }

        if ($action->type === ActionType::Cancel) {
            $this->finished_at = now();
        }

        if ($action->type->isError()) {
            $this->error_reason = Arr::get($action->properties, 'issue');
            // has-error?
        }

        if ($action->type->isInteraction()) {
            $this->last_interaction_at = now();
        }

        logger()->info(logname(), [
            'path' => request()->url(),
            'type' => $action->type->value,
            'dirty' => $this->getDirty(),
        ]);

        $this->save();

        return $this;
    }
}
