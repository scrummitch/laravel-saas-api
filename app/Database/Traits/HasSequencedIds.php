<?php

namespace App\Database\Traits;

trait HasSequencedIds
{
    public static function bootHasSequencedIds()
    {
        static::creating(function ($model) {
            $model->version_number = $model->nextIdInSequence();
            if (empty($model->version_name)) {
                $model->version_name = 'v'.$model->version_number;
            }
        });
    }

    public function nextIdInSequence()
    {
        $q = static::query()
            ->select('version_number')
            ->orderBy('version_number', 'desc')
            ->sequencing()
            ->lockForUpdate()
            ->first();

        if (is_null($q)) {
            return 1;
        }

        return $q->version_number + 1;
    }

    public function scopeSequencing(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query;
    }
}
