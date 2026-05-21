<?php

namespace App\Database;

use App\Models\Management\Organization;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Core model object which contains a bunch of nice defaults on top of the
 * framework
 */
class Model extends EloquentModel
{
    public function casts()
    {
        return property_exists($this, 'casts') ? $this->casts : [];
    }

    public static function retrieve(string|int|null $id): ?static
    {
        if (is_null($id)) {
            return null;
        }

        if (is_int($id)) {
            return (new static)->query()->find($id);
        }

        return (new static)->resolveRouteBinding($id);
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if (! request()->is('v1/*')) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        if (in_array(static::class, [Organization::class])) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        if (!method_exists($this, 'organization')) {
            return $query->where($field ?? $this->getRouteKeyName(), $value);
        }

        return $query
            ->where('organization_id', request()->user()?->organization_id)
            ->where($field ?? $this->getRouteKeyName(), $value);
    }
}
