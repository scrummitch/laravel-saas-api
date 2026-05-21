<?php

namespace App\Models\Account;

use App\Models\Intelligence\Activity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Money\Money;

class SignalBuilder
{
    protected array $metadata = [];
    protected array $dependencies = [];
    protected ?Money $amount = null;
    protected ?Carbon $effectiveAt = null;
    protected ?Carbon $createdAt = null;

    public function __construct(
        protected SignalID $type,
        protected ?Customer $customer = null
    ) {}

    public static function make(SignalID $type): self
    {
        return new self($type);
    }

    public function for(Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function amount(Money|int $amount): self
    {
        $this->amount = $amount instanceof Money ? $amount : Money::USD($amount);

        return $this;
    }

    public function at(Carbon $timestamp): self
    {
        $this->createdAt = $timestamp;

        return $this;
    }

    public function effectiveAt(?Carbon $timestamp = null): self
    {
        $this->effectiveAt = $timestamp;

        return $this;
    }

    public function keep(Model $model, ?int $quantity = null, ?float $delta = null): self
    {
        $this->dependencies[] = [
            'linkable' => $model,
            'relation_type' => 2,
            'quantity' => $quantity,
            'delta' => $delta,
        ];
        return $this;
    }

    public function from(?Model $model, ?int $quantity = null, ?float $delta = null): self
    {
        if (is_null($model)) {
            return $this;
        }

        $this->dependencies[] = [
            'linkable' => $model,
            'relation_type' => 0,
            'quantity' => $quantity,
            'delta' => $delta,
        ];

        return $this;
    }

    public function to(?Model $model, ?int $quantity = null, int $delta = null): self
    {
        if (is_null($model)) {
            return $this;
        }

        $this->dependencies[] = [
            'linkable' => $model,
            'relation_type' => 1,
            'quantity' => $quantity,
            'delta' => $delta,
        ];

        return $this;
    }

    public function withMeta(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    public function emit(): Signal
    {
        if (!$this->customer) {
            throw new \RuntimeException('Customer is required for signal');
        }

        $signal = new Signal([
            'customer_id' => $this->customer->id,
            'type' => $this->type,
            'amount_raw' => $this->amount?->getAmount(),
            'currency' => $this->amount?->getCurrency()->getCode(),
            'metadata' => $this->metadata,
            'effective_at' => $this->effectiveAt,
            'created_at' => $this->createdAt ?? now(),
        ]);

        $signal->save();

        foreach ($this->dependencies as $dep) {
            $signal->dependencies()->create([
                'linkable_type' => get_class($dep['linkable']),
                'linkable_id' => $dep['linkable']->getKey(),
                'relation_type' => $dep['relation_type'],
                'quantity' => $dep['quantity'],
                'delta' => $dep['delta'],
            ]);
        }

        // figure out the diff for purchases?
        // shows $ new revenue, in different currencies
        // shows upcoming, need to link them somehow?


        $recentActivity = Activity::query()
            ->where('customer_id', $this->customer->id)
            ->where('has_completed', true) // what about stripe checkout?
            ->latest()
            ->whereBetween('created_at', [
                now(),
                now()->subMinutes(5),
            ])
            ->get();


        // signal, looks up recent successful activity?

        if ($recentActivity) {
            $signal->activity()->associate($recentActivity);
        }

        return $signal;
    }
}
