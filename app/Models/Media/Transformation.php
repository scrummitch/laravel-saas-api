<?php

namespace App\Models\Media;

use App\Database\Model;

class Transformation extends Model
{
    protected $table = 'media_transformations';

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    protected $fillable = [
        'type',
        'name',
        'options',
        'uuid',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
