<?php

namespace App\Convert;

use App\Client\ClientAuthorization;
use App\Convert\Exceptions\EventNotAuthorized;
use App\Convert\Exceptions\EventNotValid;
use App\Intel\Enums\FlowState;
use App\Intel\Enums\ScenarioState;
use App\Models\Convert\Flow;
use App\Models\Intelligence\ActionType;
use App\Models\Intelligence\Activity;
use App\Models\Intelligence\ActivityAction;
use App\Models\Intelligence\Scenario;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class ActionEvent
{
    public Activity $activity;
    public Flow $flow;
    protected ?Scenario $scenario = null;
    protected array $entry;
    protected ?array $metadata;


    public string $id;
    public ActionType $type;
    public string $event_name;
    public Carbon $created_at;
    /**
     * @var array|\ArrayAccess|mixed
     */
    private array|null $properties = null;

    public function __construct(
        protected array $data,
        protected ClientAuthorization $auth
    )
    {
        $this->setData();
    }

    protected function setData(): void
    {
        $flowKey = Arr::get($this->data, 'flow');

        if (empty($flowKey)) {
            return;
        }

        // note: uncache using tags?
        $this->type = ActionType::make(Arr::get($this->data, 'type'));
        $orgKey = $this->auth->client->organization_id;

        // Fast cache lookup for flow using lookup_key
        $this->flow = Cache::remember(
            "{$orgKey}|flow:{$flowKey}",
            now()->addMinutes(60),
            fn () => Flow::unguarded(fn() => Flow::query()->firstOrCreate([
                'lookup_key' => $flowKey,
                'organization_id' => $this->auth->client->organization_id,
            ], [
                'current_state' => FlowState::Staged,
                'name' => implode(' ', [
                    $this->auth->client->name,
                    $flowKey
                ]),
                'triggers' => [],
            ]))
        );

        // If scenario is provided, link it
        if ($scenarioKey = Arr::get($this->data, 'scenario')) {
            $this->scenario = Cache::remember(
                "{$orgKey}|scenario:{$scenarioKey}",
                now()->addMinutes(60),
                fn () => Scenario::query()->firstOrCreate([
                    'flow_id' => $this->flow->id,
                    'lookup_key' => $scenarioKey,
                ], [
                    'current_state' => ScenarioState::Staged,
                    'display_name' => Str::title($scenarioKey),
                ])
            );
        } else {
            $scenarioKey = 'control';
            $this->scenario = Cache::remember(
                "{$orgKey}|flow:{$this->flow->id}|scenario:{$scenarioKey}",
                now()->addMinutes(60),
                fn () => Scenario::query()->firstOrCreate([
                    'flow_id' => $this->flow->id,
                    'lookup_key' => $scenarioKey,
                ],[
                    'current_state' => ScenarioState::Staged,
                    'display_name' => Str::title($scenarioKey),
                ])
            );
        }

        // Create or retrieve activity with proper state handling
        $activityKey = Arr::get($this->data, 'activity');
        $this->activity = Activity::firstOrCreate(
            [
                'uuid' => $activityKey,
                'client_id' => $this->auth->client->id,
            ],
            [
                'collector_id' => $this->auth->collector->id,
                'customer_id' => $this->auth->customer?->id,
                'scenario_id' => $this->scenario?->id,
                'current_state' => 'created',
                'started_at' => now(),
                'last_interaction_at' => now(),
            ]
        );

        // todo: entry isnt right, this will be custom per action type
        $this->properties = Arr::except(
            Arr::get($this->data, $this->type->getKey(), []),
            [
                'timestamp',
                'flow',
                'flow_id',
                'scenario',
                'scenario_id',
            ]
        );
        $this->metadata = Arr::get($this->data, 'metadata');
    }

    public function validate(): void
    {
        $this->assert(
            Arr::has($this->data, ['type', 'activity', 'flow']),
            'Required fields missing from action event '.json_encode($this->data)
        );

        if (Arr::has($this->data, 'type')) {
            $this->validateActionType();
        }
    }

    protected function validateActionType(): void
    {
        $type = ActionType::make(Arr::get($this->data, 'type'));

//        match ($type) {
//            ActionType::Error => $this->assert(
//                Arr::has($this->data, 'error'),
//                'Error action requires error message'
//            ),
//            ActionType::Preauthorize => $this->assert(
//                Arr::has($this->data, ['provider', 'status']),
//                'Preauthorize requires provider and status'
//            ),
//            default => null
//        };
    }

    public function resolve(): ActivityAction
    {
        $type = ActionType::make(Arr::get($this->data, 'type'));

        $action =  new ActivityAction([
            'activity_id' => $this->activity->id,
            'event_id' => Arr::get($this->data, 'id'),
            'event_name' => $type === ActionType::Custom ? Arr::get($this->data, 'type') : null,
            'type' => $type ?? ActionType::Custom,
            'properties' => $this->properties,
            'metadata' => $this->metadata,
        ]);

        $action->save();

        return $action;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    protected function assert($assertion, ?string $exception = null, ?string $message = null): static
    {
        if ($exception && $message === null && ! is_a($exception, Throwable::class, true)) {
            [$message, $exception] = [$exception, null];
        }

        if ($message === null) {
            $message = 'The event is not valid';
        }

        if ($exception === null) {
            $caller = Arr::first(
                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2),
                static fn ($trace) => ($trace['function'] ?? 'assert') !== 'assert'
            )['function'] ?? 'validate';

            $exception = match (true) {
                Str::startsWith($caller, 'authorize') => EventNotAuthorized::class,
                Str::startsWith($caller, 'validate') => EventNotValid::class,
                default => EventNotValid::class,
            };
        }

        $result = (bool) value($assertion, $this);

        if ($result === true) {
            return $this;
        }

        throw new $exception($message);
    }
}
