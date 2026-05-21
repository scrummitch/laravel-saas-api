<?php

namespace App\Database\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;

trait HasNiceUlids
{
    /**
     * Initialize the trait.
     *
     * @return void
     */
    public function initializeHasNiceUlids()
    {
        $this->usesUniqueIds = true;
    }

    public function scopeForUlid(Builder $query, string $id): void
    {
        $query->where('ulid', $this->getId($id));
    }

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array
     */
    public function uniqueIds()
    {
        return [$this->getRouteKeyName()];
    }

    public function getRouteKeyName()
    {
        return 'ulid';
    }

    public function getRouteKey(): string
    {
        return implode(
            '_',
            array_filter([
                $this->getModelKey(),
                (new Ulid($this->{$this->getRouteKeyName()}))->toBase58(),
            ])
        );
    }

    protected function getModelKey(): ?string
    {
        return $this->getMorphClass();
    }

    /**
     * Generate a new ULID for the model.
     *
     * @return string
     */
    public function newUniqueId()
    {
        return (string) Str::ulid();
    }

    public static function getId($value)
    {
        $value = Str::afterLast($value, '_');

        try {
            return strval(Ulid::fromBase58($value));
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param  \Illuminate\Database\Eloquent\Model|Relation  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return Relation
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $value = $this->getId($value);

        if ($field && in_array($field, $this->uniqueIds()) && ! Str::isUlid($value)) {
            throw (new ModelNotFoundException)->setModel(get_class($this), $value);
        }

        if (! $field && in_array($this->getRouteKeyName(), $this->uniqueIds()) && ! Str::isUlid($value)) {
            throw (new ModelNotFoundException)->setModel(get_class($this), $value);
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }

    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function getKeyType()
    {
        if (in_array($this->getKeyName(), $this->uniqueIds())) {
            return 'string';
        }

        return $this->keyType;
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        if (in_array($this->getKeyName(), $this->uniqueIds())) {
            return false;
        }

        return $this->incrementing;
    }
}
