<?php

namespace App\Database\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasVersionedLookupKey
{
    public static function bootHasVersionedLookupKey()
    {
        static::saving(function (Model $model) {
            if (empty($model->getLookupKey())) {
                $model->setLookupKey(Str::slug($model->getLookupKeyableString(), $model->getSlugSeparator()));
            }
        });
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        [$lookupKey, $versionNumber] = array_pad(explode('@v', $value), 2, 1);

        $query = $query->where('version_number', intval($versionNumber));

        return parent::resolveRouteBindingQuery($query, $lookupKey, $field);
    }

    public function getRouteKey()
    {
        return implode(
            '@v',
            array_filter([$this->getLookupKey(), $this->getAttribute('version_number')])
        );
    }

    public function getLookupKey()
    {
        return $this->getAttribute('lookup_key');
    }

    /**
     * Set the slug to the given value.
     *
     * @param  string  $value
     * @return $this
     */
    public function setLookupKey($value)
    {
        $this->setAttribute($this->getRouteKeyName(), $value);

        return $this;
    }

    public function getRouteKeyName()
    {
        return 'lookup_key';
    }

    /**
     * Get the string to create a slug from.
     *
     * @return string
     */
    protected function getLookupKeyableString()
    {
        return $this->getAttribute('name');
    }

    /**
     * The character to use to separate words.
     *
     * @return string
     */
    protected function getSlugSeparator()
    {
        return '_';
    }
}
