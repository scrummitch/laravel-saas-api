<?php

namespace App\Convert\DataObjects;

use Illuminate\Support\Arr;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class WorkflowTrigger extends ValidatedDTO
{
    public string $listen;

    public ?string $event;

    public ?string $qualifier;

    public array $props;

    protected function defaults(): array
    {
        return [
            'listen' => 'attachment',
            'click' => null,
            'qualifier' => 'any',
            'props' => [],
        ];
    }

    public function descriptor(): ?string
    {
        return match ($this->listen) {
            'attachment' => value(function () {
                return match ($this->qualifier) {
                    'findButtonByText' => 'Click Button ('.Arr::get($this->props, 'text').')',
                };
            }),
            'custom' => $this->event,
            default => null,
        };
    }

    protected function casts(): array
    {
        return [];
    }

    protected function rules(): array
    {
        return [
            'listen' => 'required|string',
            'event' => 'required|string',
            'qualifier' => 'string',
            'props' => 'array',
        ];
    }
}
