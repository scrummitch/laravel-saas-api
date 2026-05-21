<?php

namespace App\Database\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @var string $lookup_key
 */
trait HasLookupKey
{
    /**
     * Boot the sluggable trait for a model.
     *
     * @return void
     */
    public static function bootHasLookupKey()
    {
        static::saving(function (Model $model) {
            if (empty($model->getLookupKey())) {
                $model->setLookupKey(Str::slug($model->getLookupKeyableString(), $model->getSlugSeparator()));
            }
        });
    }

    /**
     * Get the current slug value.
     *
     * @return string
     */
    public function getLookupKey()
    {
        return $this->getAttribute($this->getRouteKeyName());
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
